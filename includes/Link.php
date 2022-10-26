<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\AtEase\AtEase;

/**
 * Link class
 * Functions for managing Link pages.
 */
class Link {

	/** @var string[] */
	public static $link_types = [
		1 => 'Arrest Report',
		2 => 'Awesome',
		3 => 'Cool',
		4 => 'Funny',
		6 => 'Interesting',
		7 => 'Obvious',
		8 => 'OMG WTF?!?',
		9 => 'Rumor',
		10 => 'Scary',
		11 => 'Stupid'
	];

	/**
	 * Get the full URL to the link submission page.
	 *
	 * @return string
	 */
	public static function getSubmitLinkURL() {
		$title = SpecialPage::getTitleFor( 'LinkSubmit' );
		return htmlspecialchars( $title->getFullURL(), ENT_QUOTES );
	}

	/**
	 * Get the full URL to the link approval page.
	 *
	 * @return string
	 */
	public static function getLinkAdminURL() {
		$title = SpecialPage::getTitleFor( 'LinkApprove' );
		return htmlspecialchars( $title->getFullURL(), ENT_QUOTES );
	}

	/**
	 * Get the full URL to the link overview ("home") page.
	 *
	 * @return string
	 */
	public static function getHomeLinkURL() {
		$title = SpecialPage::getTitleFor( 'LinksHome' );
		return htmlspecialchars( $title->getFullURL(), ENT_QUOTES );
	}

	/**
	 * Checks if user is allowed to access LinkFilter's special pages
	 *
	 * @param User $user
	 * @return bool True if s/he has linkadmin permission or is in the
	 *                  linkadmin user group, else false
	 */
	public static function canAdmin( User $user ) {
		if (
			$user->isAllowed( 'linkadmin' ) ||
			in_array( 'linkadmin', MediaWikiServices::getInstance()->getUserGroupManager()->getUserGroups( $user ) )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if $code is an URL.
	 *
	 * @param string $code
	 * @return bool True if it's an URL, otherwise false
	 */
	public static function isURL( $code ) {
		return preg_match( '%^(?:http|https|ftp)://(?:www\.)?.*$%i', $code );
	}

	/**
	 * Adds a link to the database table.
	 *
	 * @param string $title Link title as supplied by the user
	 * @param string $desc Link description as supplied by the user
	 * @param string $url The actual URL
	 * @param int $type Link type, either from the global variable or from
	 *						this class' static array.
	 * @param User $user
	 */
	public function addLink( $title, $desc, $url, $type, User $user ) {
		$dbw = wfGetDB( DB_PRIMARY );

		AtEase::suppressWarnings();
		$date = date( 'Y-m-d H:i:s' );
		AtEase::restoreWarnings();

		$dbw->insert(
			'link',
			[
				'link_name' => $title,
				'link_page_id' => 0,
				'link_url' => $url,
				'link_description' => $desc,
				'link_type' => intval( $type ),
				'link_status' => 0,
				'link_submit_date' => $dbw->timestamp( $date ),
				'link_submitter_actor' => $user->getActorId()
			],
			__METHOD__
		);

		// If SocialProfile extension is installed, increase social statistics.
		if ( class_exists( 'UserStatsTrack' ) ) {
			$stats = new UserStatsTrack( $user->getId(), $user->getName() );
			$stats->incStatField( 'links_submitted' );
		}
	}

	/**
	 * Approve a link with the given ID and perform all the related magic.
	 * This includes creating the Link: page, updating the database and updating
	 * social statistics (when SocialProfile is installed & active).
	 *
	 * @param int $id Link identifier
	 */
	public function approveLink( $id ) {
		$link = $this->getLink( $id );

		// Create the wiki page for the newly-approved link
		$linkTitle = Title::makeTitleSafe( NS_LINK, $link['title'] );
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $linkTitle );
		} else {
			$page = WikiPage::factory( $linkTitle );
		}
		$pageContent = ContentHandler::makeContent(
			$link['url'],
			$page->getTitle()
		);
		$summary = wfMessage( 'linkfilter-edit-summary' )->inContentLanguage()->text();
		if ( method_exists( $page, 'doUserEditContent' ) ) {
			// MW 1.36+
			$page->doUserEditContent(
				$pageContent,
				RequestContext::getMain()->getUser(),
				$summary
			);
		} else {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$page->doEditContent( $pageContent, $summary );
		}
		$newPageID = $page->getID();

		// Tie link record to wiki page
		$dbw = wfGetDB( DB_PRIMARY );

		AtEase::suppressWarnings();
		$date = date( 'Y-m-d H:i:s' );
		AtEase::restoreWarnings();

		$dbw->update(
			'link',
			[
				'link_page_id' => $newPageID,
				'link_approved_date' => $date
			],
			[ 'link_id' => intval( $id ) ],
			__METHOD__
		);

		if ( class_exists( 'UserStatsTrack' ) ) {
			$user = User::newFromActorId( $link['actor'] );
			$userId = $user->getId();
			$userName = $user->getName();

			$stats = new UserStatsTrack( $userId, $userName );
			$stats->incStatField( 'links_approved' );
		}
	}

	/**
	 * Gets a link entry by given page ID.
	 *
	 * @param int $pageId Page ID number
	 * @return array
	 */
	public function getLinkByPageID( $pageId ) {
		if ( !is_numeric( $pageId ) ) {
			return [];
		}

		$dbr = wfGetDB( DB_REPLICA );

		$row = $dbr->selectRow(
			'link',
			[
				'link_id', 'link_name', 'link_url', 'link_description',
				'link_type', 'link_status', 'link_page_id',
				'link_submitter_actor'
			],
			[ 'link_page_id' => $pageId ],
			__METHOD__
		);

		$link = [];
		if ( $row ) {
			$link['id'] = $row->link_id;
			$link['title'] = $row->link_name;
			$link['url'] = $row->link_url;
			$link['type'] = $row->link_type;
			$link['description'] = $row->link_description;
			$link['type_name'] = self::getLinkType( $row->link_type );
			$link['status'] = $row->link_status;
			$link['page_id'] = $row->link_page_id;
			$link['actor'] = $row->link_submitter_actor;
		}

		return $link;
	}

	/**
	 * @return array
	 */
	static function getLinkTypes() {
		global $wgLinkFilterTypes;

		if ( is_array( $wgLinkFilterTypes ) ) {
			return $wgLinkFilterTypes;
		} else {
			return self::$link_types;
		}
	}

	/**
	 * @param int $index Numerical index representing the link filter type
	 * @return string Link type name or nothing
	 */
	static function getLinkType( $index ) {
		global $wgLinkFilterTypes;

		if (
			is_array( $wgLinkFilterTypes ) &&
			!empty( $wgLinkFilterTypes[$index] )
		) {
			return $wgLinkFilterTypes[$index];
		} elseif ( isset( self::$link_types[$index] ) ) {
			return self::$link_types[$index];
		} else {
			return '';
		}
	}

	/**
	 * Gets a link entry by given link ID number.
	 *
	 * @param int $id Link ID number
	 * @return array
	 */
	public function getLink( $id ) {
		if ( !is_numeric( $id ) ) {
			return [];
		}

		$dbr = wfGetDB( DB_REPLICA );

		$row = $dbr->selectRow(
			'link',
			[
				'link_id', 'link_name', 'link_url', 'link_description',
				'link_type', 'link_status', 'link_page_id',
				'link_submitter_actor'
			],
			[ 'link_id' => $id ],
			__METHOD__
		);

		$link = [];
		if ( $row ) {
			$link['id'] = $row->link_id;
			$link['title'] = $row->link_name;
			$link['url'] = $row->link_url;
			$link['description'] = self::parseDescription( $row->link_description );
			$link['type'] = $row->link_type;
			$link['type_name'] = self::getLinkType( $row->link_type );
			$link['status'] = $row->link_status;
			$link['page_id'] = $row->link_page_id;
			$link['actor'] = $row->link_submitter_actor;
		}

		return $link;
	}

	/**
	 * Parse link description. Basically a wrapper around OutputPage's parseAsContent
	 * method, but also removes the resulting <p>...</p> tags parseAsContent's
	 * return value is wrapped into, because they're awfully pointless and probably
	 * more harmful than useful.
	 *
	 * @param string $desc User-submitted link description from the link DB table
	 * @return string Cleaned-up and fully parsed link description
	 */
	public static function parseDescription( $desc ) {
		global $wgOut;
		return str_replace( [ '<p>', '</p>' ], '', $wgOut->parseAsContent( $desc, false ) );
	}
}
