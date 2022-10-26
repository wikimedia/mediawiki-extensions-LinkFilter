<?php
/**
 * Hooked functions for the LinkFilter extension.
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;

class LinkFilterHooks {

	/**
	 * This function is called after a page has been moved successfully to
	 * update the LinkFilter entries.
	 *
	 * @param MediaWiki\Linker\LinkTarget $old Original/old title
	 * @param MediaWiki\Linker\LinkTarget $new New title
	 * @param MediaWiki\User\UserIdentity $userIdentity User (object) who performed the page move
	 * @param int $oldId Old page ID
	 * @param int $newId New page ID
	 * @param string $reason User-supplied reason for moving the page
	 * @param MediaWiki\Revision\RevisionRecord $revision
	 */
	public static function updateLinkFilter(
		MediaWiki\Linker\LinkTarget $old,
		MediaWiki\Linker\LinkTarget $new,
		MediaWiki\User\UserIdentity $userIdentity,
		int $oldId,
		int $newId,
		string $reason,
		MediaWiki\Revision\RevisionRecord $revision
	) {
		if ( $old->getNamespace() == NS_LINK ) {
			$dbw = wfGetDB( DB_PRIMARY );
			$dbw->update(
				'link',
				[ 'link_name' => $new->getText() ],
				[ 'link_page_id' => $oldId ],
				__METHOD__
			);
		}
	}

	/**
	 * Whenever a page in the NS_LINK namespace is deleted, update the records
	 * in the link table.
	 *
	 * @param Article|WikiPage &$article Article object (or child class) being deleted
	 * @param User &$user User (object) performing the page deletion
	 * @param string $reason User-supplied reason for the deletion
	 */
	public static function deleteLinkFilter( &$article, &$user, $reason ) {
		if ( $article->getTitle()->getNamespace() == NS_LINK ) {
			// Delete link record
			$dbw = wfGetDB( DB_PRIMARY );
			$dbw->update(
				'link',
				[ 'link_status' => LinkStatus::REJECTED ],
				[ 'link_page_id' => $article->getID() ],
				__METHOD__
			);
		}
	}

	/**
	 * Hooked into ArticleFromTitle hook.
	 * Calls LinkPage instead of standard article for pages in the NS_LINK
	 * namespace.
	 *
	 * @param Title &$title Title object associated with the current page
	 * @param Article|WikiPage &$article Article object (or child class) associated with
	 *                         the current page
	 * @param RequestContext $context
	 */
	public static function linkFromTitle( &$title, &$article, $context ) {
		if ( $title->getNamespace() == NS_LINK ) {
			$out = $context->getOutput();
			if ( method_exists( $out, 'disableClientCache' ) ) {
				// MW 1.38+
				$out->disableClientCache();
			} else {
				// @phan-suppress-next-line PhanParamTooMany
				$out->enableClientCache( false );
			}

			if ( $context->getRequest()->getVal( 'action' ) == 'edit' ) {
				if ( $title->getArticleID() == 0 ) {
					$create = SpecialPage::getTitleFor( 'LinkSubmit' );
					$out->redirect(
						$create->getFullURL( '_title=' . $title->getText() )
					);
				} else {
					$update = SpecialPage::getTitleFor( 'LinkEdit' );
					$out->redirect(
						$update->getFullURL( 'id=' . $title->getArticleID() )
					);
				}
			}

			// Add CSS
			$out->addModuleStyles( [ 'ext.linkFilter.styles' ] );

			$article = new LinkPage( $title );
		}
	}

	/**
	 * Registers the <linkfilter> parser hook with MediaWiki's Parser.
	 *
	 * @param Parser &$parser
	 */
	public static function registerLinkFilterHook( &$parser ) {
		$parser->setHook( 'linkfilter', [ 'LinkFilterHooks', 'renderLinkFilterHook' ] );
	}

	/**
	 * Callback function for registerLinkFilterHook.
	 *
	 * @param string $input User-supplied input [unused]
	 * @param array $args Arguments supplied to the hook, e.g. <linkfilter count="5" />
	 * @param Parser $parser
	 * @return string HTML suitable for output
	 */
	public static function renderLinkFilterHook( $input, $args, Parser $parser ) {
		$pOutput = $parser->getOutput();
		$pOutput->updateCacheExpiry( 0 );

		// Add CSS
		$pOutput->addModuleStyles( [ 'ext.linkFilter.styles' ] );

		if ( isset( $args['count'] ) ) {
			$count = intval( $args['count'] );
		} else {
			$count = 10;
		}

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $cache->makeKey( 'linkfilter', $count );
		$data = $cache->get( $key );

		if ( $data ) {
			wfDebugLog( 'LinkFilter', "Loaded linkfilter hook from cache\n" );
			$links = $data;
		} else {
			wfDebugLog( 'LinkFilter', "Loaded linkfilter hook from DB\n" );
			$l = new LinkList();
			$links = $l->getLinkList( LinkStatus::APPROVED, 0, $count, 1, 'link_approved_date' );
			$cache->set( $key, $links, 60 * 5 );
		}

		$link_submit = SpecialPage::getTitleFor( 'LinkSubmit' );
		$link_all = SpecialPage::getTitleFor( 'LinksHome' );

		// @todo FIXME: the wfMessage calls would need a ->setContext( $context )
		// to avoid a GlobalTitleFail being logged into the debug log, but I can't see
		// a way to get a valid context object here...
		$output = '<div>

				<div class="linkfilter-links">
					<a href="' . htmlspecialchars( $link_submit->getFullURL(), ENT_QUOTES ) . '">' .
						wfMessage( 'linkfilter-submit' )->escaped() .
					'</a> / <a href="' . htmlspecialchars( $link_all->getFullURL(), ENT_QUOTES ) . '">' .
						wfMessage( 'linkfilter-all' )->escaped() . '</a>';

		if ( method_exists( $parser, 'getUserIdentity' ) ) {
			// MW 1.36+
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity( $parser->getUserIdentity() );
		} else {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$user = $parser->getUser();
		}

		// Show a link to the link administration panel for privileged users
		if ( Link::canAdmin( $user ) ) {
			$output .= ' / <a href="' . Link::getLinkAdminURL() . '">' .
				wfMessage( 'linkfilter-approve-links' )->escaped() . '</a>';
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

		// This is 100% certified all-natural bullshit; phan just hates $link['title'] being pre-escaped
		// _but_ it also hates it being escaped closer to the output. It's a lose-lose situation for the poor developer.
		// Same thing happens in SpecialLinkApprove.php#execute too.
		// @phan-suppress-next-line SecurityCheck-XSS
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
			$dbw = wfGetDB( DB_PRIMARY );
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
			$dbw = wfGetDB( DB_PRIMARY );
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
	 * @param DatabaseUpdater $updater
	 */
	public static function applySchemaChanges( $updater ) {
		$sqlPath = __DIR__ . '/../sql';
		$db = $updater->getDB();

		$file = $sqlPath . '/link.sql';
		$isPostgreSQL = ( $db->getType() === 'postgres' );
		if ( $isPostgreSQL ) {
			$file = $sqlPath . '/link.postgres.sql';
		}
		$updater->addExtensionTable( 'link', $file );

		// If the user_stats table exists (=SocialProfile is installed), check if
		// it has the columns we need and if not, create them.
		if ( $db->tableExists( 'user_stats' ) ) {
			$ext = $isPostgreSQL ? '.postgres.sql' : '.sql';
			if ( !$db->fieldExists( 'user_stats', 'stats_links_submitted', __METHOD__ ) ) {
				$updater->addExtensionField( 'user_stats', 'stats_links_submitted', $sqlPath . '/patch-add_stats_links_submitted_column' . $ext );
			}
			if ( !$db->fieldExists( 'user_stats', 'stats_links_approved', __METHOD__ ) ) {
				$updater->addExtensionField( 'user_stats', 'stats_links_approved', $sqlPath . '/patch-add_stats_links_approved_column' . $ext );
			}
		}

		// Actor support (see T227345)
		if ( $db->tableExists( 'link' ) && !$db->fieldExists( 'link', 'link_submitter_actor', __METHOD__ ) ) {
			$updater->addExtensionField( 'link', 'link_submitter_actor', $sqlPath . '/patch-add_link_submitter_actor_column.sql' );
		}
	}
}
