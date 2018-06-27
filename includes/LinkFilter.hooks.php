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
	 * @param Title $title Original/old Title
	 * @param Title $newTitle New Title
	 * @param User $user User (object) who performed the page move
	 * @param int $oldId Old page ID
	 * @param int $newId New page ID
	 */
	public static function updateLinkFilter( &$title, &$newTitle, $user, $oldId, $newId ) {
		if ( $title->getNamespace() == NS_LINK ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'link',
				[ 'link_name' => $newTitle->getText() ],
				[ 'link_page_id' => intval( $oldId ) ],
				__METHOD__
			);
		}
	}

	/**
	 * Whenever a page in the NS_LINK namespace is deleted, update the records
	 * in the link table.
	 *
	 * @param Article|WikiPage $article Article object (or child class) being deleted
	 * @param User $user User (object) performing the page deletion
	 * @param string $reason User-supplied reason for the deletion
	 */
	public static function deleteLinkFilter( &$article, &$user, $reason ) {
		if ( $article->getTitle()->getNamespace() == NS_LINK ) {
			// Delete link record
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'link',
				[ 'link_status' => LinkStatus::REJECTED ],
				[ 'link_page_id' => intval( $article->getID() ) ],
				__METHOD__
			);
		}
	}

	/**
	 * Hooked into ArticleFromTitle hook.
	 * Calls LinkPage instead of standard article for pages in the NS_LINK
	 * namespace.
	 *
	 * @param Title $title Title object associated with the current page
	 * @param Article|WikiPage $article Article object (or child class) associated with
	 *                         the current page
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
	}

	/**
	 * Registers the <linkfilter> parser hook with MediaWiki's Parser.
	 *
	 * @param Parser $parser
	 */
	public static function registerLinkFilterHook( &$parser ) {
		$parser->setHook( 'linkfilter', [ 'LinkFilterHooks', 'renderLinkFilterHook' ] );
	}

	/**
	 * Callback function for registerLinkFilterHook.
	 */
	public static function renderLinkFilterHook( $input, $args, $parser ) {
		global $wgMemc, $wgOut;

		$parser->getOutput()->updateCacheExpiry( 0 );

		// Add CSS (ParserOutput class only has addModules(), not
		// addModuleStyles() or addModuleScripts()...strange)
		$wgOut->addModuleStyles( 'ext.linkFilter.styles' );

		if ( isset( $args['count'] ) ) {
			$count = intval( $args['count'] );
		} else {
			$count = 10;
		}

		$key = $wgMemc->makeKey( 'linkfilter', $count );
		$data = $wgMemc->get( $key );

		if ( $data ) {
			wfDebugLog( 'LinkFilter', "Loaded linkfilter hook from cache\n" );
			$links = $data;
		} else {
			wfDebugLog( 'LinkFilter', "Loaded linkfilter hook from DB\n" );
			$l = new LinkList();
			$links = $l->getLinkList( LinkStatus::APPROVED, '', $count, 1, 'link_approved_date' );
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
	 * @param Comment $commentClass Instance of the Comment class
	 * @param int $commentID Comment ID number
	 * @param int $pageID Page ID number
	 */
	public static function onCommentAdd( $commentClass, $commentID, $pageID ) {
		if ( $commentID && $pageID ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'link',
				[ 'link_comment_count = link_comment_count+1' ],
				[ 'link_page_id' => intval( $pageID ) ],
				__METHOD__
			);
		}
	}

	/**
	 * Does the opposite of the above hook. Parameters are the same.
	 *
	 * @param Comment $commentClass Instance of Comment class
	 * @param int $commentID Comment ID number
	 * @param int $pageID Page ID number
	 */
	public static function onCommentDelete( $commentClass, $commentID, $pageID ) {
		if ( $commentID && $pageID ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'link',
				[ 'link_comment_count = link_comment_count-1' ],
				[ 'link_page_id' => intval( $pageID ) ],
				__METHOD__
			);
		}
	}

	/**
	 * Applies the schema changes when the user runs maintenance/update.php.
	 *
	 * @todo FIXME: this should check if user_stats DB table exists and if it
	 *              has the necessary stats_links_submitted and
	 *              stats_links_approved columns; if not, apply
	 *              patch-columns_for_user_stats.sql against the database
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function applySchemaChanges( $updater ) {
		$file = __DIR__ . '/../sql/link.sql';
		$updater->addExtensionTable( 'link', $file );
	}

	/**
	 * For the Renameuser extension.
	 *
	 * @param RenameuserSQL $renameUserSQL
	 */
	public static function onUserRename( $renameUserSQL ) {
		$renameUserSQL->tables['link'] = [
			'link_submitter_user_name', 'link_submitter_user_id'
		];
	}

	/**
	 * Register the canonical names for our namespace and its talkspace.
	 *
	 * @param array $list Array of namespace numbers with corresponding
	 *                     canonical names
	 */
	public static function onCanonicalNamespaces( &$list ) {
		$list[NS_LINK] = 'Link';
		$list[NS_LINK_TALK] = 'Link_talk';
	}
}
