<?php
/**
 * Hooked functions for the LinkFilter extension.
 *
 * @file
 * @ingroup Extensions
 */
class LinkFilterHooks {

	/**
	 * This function is called after a page has been moved successfully to
	 * update the LinkFilter entries.
	 *
	 * @param $title Object: Title object
	 * @param $newTitle Object: Title obejct
	 * @param $user Object: User object ($wgUser)
	 * @param $oldId Integer
	 * @param $newId Integer
	 * @return Boolean: true
	 */
	public static function updateLinkFilter( &$title, &$newTitle, $user, $oldId, $newId ) {
		if ( $title->getNamespace() == NS_LINK ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'link',
				array( 'link_name' => $newTitle->getText() ),
				array( 'link_page_id' => intval( $oldId ) ),
				__METHOD__
			);
		}
		return true;
	}

	/**
	 * Whenever a page in the NS_LINK namespace is deleted, update the records
	 * in the link table.
	 *
	 * @param $article Object: Article object (or child class)
	 * @param $user Object: User object ($wgUser)
	 * @param $reason String: user-supplied reason for the deletion
	 * @return Boolean: true
	 */
	public static function deleteLinkFilter( &$article, &$user, $reason ) {
		if ( $article->getTitle()->getNamespace() == NS_LINK ) {
			// Delete link record
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'link',
				/* SET */array( 'link_status' => Link::$REJECTED_STATUS ),
				/* WHERE */array( 'link_page_id' => intval( $article->getID() ) ),
				__METHOD__
			);
		}

		return true;
	}

	/**
	 * Hooked into ArticleFromTitle hook.
	 * Calls LinkPage instead of standard article for pages in the NS_LINK
	 * namespace.
	 *
	 * @param $title Object: Title object associated with the current page
	 * @param $article Object: Article object (or child class) associated with
	 *                         the current page
	 * @return Boolean: true
	 */
	public static function linkFromTitle( &$title, &$article ) {
		if ( $title->getNamespace() == NS_LINK ) {
			global $wgRequest, $wgOut;
			$wgOut->enableClientCache( false );

			if ( $wgRequest->getVal( 'action' ) == 'edit' ) {
				if ( $title->getArticleID() == 0 ) {
					$create = SpecialPage::getTitleFor( 'LinkSubmit' );
					$wgOut->redirect(
						$create->getFullURL( '_title=' . $title->getText() )
					);
				} else {
					$update = SpecialPage::getTitleFor( 'LinkEdit' );
					$wgOut->redirect(
						$update->getFullURL( 'id=' . $title->getArticleID() )
					);
				}
			}

			// Add CSS
			$wgOut->addModuleStyles( 'ext.linkFilter.styles' );

			$article = new LinkPage( $title );
		}

		return true;
	}

	/**
	 * Registers the <linkfilter> parser hook with MediaWiki's Parser.
	 *
	 * @param $parser Parser
	 * @return Boolean: true
	 */
	public static function registerLinkFilterHook( &$parser ) {
		$parser->setHook( 'linkfilter', array( 'LinkFilterHooks', 'renderLinkFilterHook' ) );
		return true;
	}

	/**
	 * Callback function for registerLinkFilterHook.
	 */
	public static function renderLinkFilterHook( $input, $args, $parser ) {
		global $wgMemc, $wgOut;

		$parser->disableCache();

		// Add CSS (ParserOutput class only has addModules(), not
		// addModuleStyles() or addModuleScripts()...strange)
		$wgOut->addModuleStyles( 'ext.linkFilter.styles' );

		if ( isset( $args['count'] ) ) {
			$count = intval( $args['count'] );
		} else {
			$count = 10;
		}

		$key = wfMemcKey( 'linkfilter', $count );
		$data = $wgMemc->get( $key );

		if ( $data ) {
			wfDebugLog( 'LinkFilter', "Loaded linkfilter hook from cache\n" );
			$links = $data;
		} else {
			wfDebugLog( 'LinkFilter', "Loaded linkfilter hook from DB\n" );
			$l = new LinkList();
			$links = $l->getLinkList( Link::$APPROVED_STATUS, '', $count, 1, 'link_approved_date' );
			$wgMemc->set( $key, $links, 60 * 5 );
		}

		$link_submit = SpecialPage::getTitleFor( 'LinkSubmit' );
		$link_all = SpecialPage::getTitleFor( 'LinksHome' );

		$output = '<div>

				<div class="linkfilter-links">
					<a href="' . htmlspecialchars( $link_submit->getFullURL(), ENT_QUOTES ) . '">' .
						wfMessage( 'linkfilter-submit' )->plain() .
					'</a> / <a href="' . htmlspecialchars( $link_all->getFullURL(), ENT_QUOTES ) . '">' .
						wfMessage( 'linkfilter-all' )->plain() . '</a>';

		// Show a link to the link administration panel for privileged users
		if ( Link::canAdmin() ) {
			$output .= ' / <a href="' . Link::getLinkAdminURL() . '">' .
				wfMessage( 'linkfilter-approve-links' )->plain() . '</a>';
		}

		$output .= '</div>
				<div class="visualClear"></div>';

		foreach ( $links as $link ) {
			$output .= '<div class="link-item-hook">
			<span class="link-item-hook-type">' .
				$link['type_name'] .
			'</span>
			<span class="link-item-hook-url">
				<a href="' . $link['wiki_page'] . '" rel="nofollow">' .
					$link['title'] .
				'</a>
			</span>
			<span class="link-item-hook-page">
				<a href="' . $link['wiki_page'] . '">(' .
					wfMessage(
						'linkfilter-comments',
						$link['comments']
					)->parse() . ')</a>
			</span>
		</div>';
		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * Updates the link_comment_count field on link table whenever someone
	 * comments on a link.
	 *
	 * @param $commentClass Object: instance of Comment class
	 * @param $commentID Integer: comment ID number
	 * @param $pageID Integer: ID number of the page
	 * @return Boolean: true
	 */
	public static function onCommentAdd( $commentClass, $commentID, $pageID ) {
		if ( $commentID && $pageID ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'link',
				/* SET */array( 'link_comment_count = link_comment_count+1' ),
				/* WHERE */array( 'link_page_id' => intval( $pageID ) ),
				__METHOD__
			);
		}
		return true;
	}

	/**
	 * Does the opposite of the above hook. Parameters are the same.
	 *
	 * @param $commentClass Object: instance of Comment class
	 * @param $commentID Integer: comment ID number
	 * @param $pageID Integer: ID number of the page
	 * @return Boolean: true
	 */
	public static function onCommentDelete( $commentClass, $commentID, $pageID ) {
		if ( $commentID && $pageID ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'link',
				/* SET */array( 'link_comment_count = link_comment_count-1' ),
				/* WHERE */array( 'link_page_id' => intval( $pageID ) ),
				__METHOD__
			);
		}
		return true;
	}

	/**
	 * Applies the schema changes when the user runs maintenance/update.php.
	 *
	 * @todo FIXME: this should check if user_stats DB table exists and if it
	 *              has the necessary stats_links_submitted and
	 *              stats_links_approved columns; if not, apply
	 *              patch-columns_for_user_stats.sql against the database
	 *
	 * @param $updater DatabaseUpdater
	 * @return Boolean: true
	 */
	public static function applySchemaChanges( $updater ) {
		$file = __DIR__ . '/../sql/link.sql';
		$updater->addExtensionUpdate( array( 'addTable', 'link', $file, true ) );
		return true;
	}

	/**
	 * For the Renameuser extension.
	 *
	 * @param $renameUserSQL
	 * @return Boolean: true
	 */
	public static function onUserRename( $renameUserSQL ) {
		$renameUserSQL->tables['link'] = array(
			'link_submitter_user_name', 'link_submitter_user_id'
		);
		return true;
	}

	/**
	 * Register the canonical names for our namespace and its talkspace.
	 *
	 * @param $list Array: array of namespace numbers with corresponding
	 *                     canonical names
	 * @return Boolean: true
	 */
	public static function onCanonicalNamespaces( &$list ) {
		$list[NS_LINK] = 'Link';
		$list[NS_LINK_TALK] = 'Link_talk';
		return true;
	}
}
