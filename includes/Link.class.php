<?php
/**
 * Link class
 * Functions for managing Link pages.
 */
class Link {

	/**
	 * Constructor
	 * @private
	 */
	/* private */ function __construct() {
	}

	static $OPEN_STATUS = 0;
	static $APPROVED_STATUS = 1;
	static $REJECTED_STATUS = 2;

	static $link_types = array(
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
	);

	public static function getSubmitLinkURL() {
		$title = SpecialPage::getTitleFor( 'LinkSubmit' );
		return htmlspecialchars( $title->getFullURL(), ENT_QUOTES );
	}

	public static function getLinkAdminURL() {
		$title = SpecialPage::getTitleFor( 'LinkApprove' );
		return htmlspecialchars( $title->getFullURL(), ENT_QUOTES );
	}

	public static function getHomeLinkURL() {
		$title = SpecialPage::getTitleFor( 'LinksHome' );
		return htmlspecialchars( $title->getFullURL(), ENT_QUOTES );
	}

	/**
	 * Checks if user is allowed to access LinkFilter's special pages
	 *
	 * @return Boolean: true if s/he has linkadmin permission or is in the
	 *                  linkadmin user group, else false
	 */
	public static function canAdmin() {
		global $wgUser;

		if (
			$wgUser->isAllowed( 'linkadmin' ) ||
			in_array( 'linkadmin', $wgUser->getGroups() )
		)
		{
			return true;
		}

		return false;
	}

	/**
	 * Checks if $code is an URL.
	 *
	 * @return Boolean: true if it's an URL, otherwise false
	 */
	public static function isURL( $code ) {
		return preg_match( '%^(?:http|https|ftp)://(?:www\.)?.*$%i', $code ) ? true : false;
	}

	/**
	 * Adds a link to the database table.
	 *
	 * @param $title String: link title as supplied by the user
	 * @param $desc String: link description as supplied by the user
	 * @param $url String: the actual URL
	 * @param $type Integer: link type, either from the global variable or from
	 *						this class' static array.
	 */
	public function addLink( $title, $desc, $url, $type ) {
		global $wgUser;

		$dbw = wfGetDB( DB_MASTER );

		wfSuppressWarnings();
		$date = date( 'Y-m-d H:i:s' );
		wfRestoreWarnings();

		$dbw->insert(
			'link',
			array(
				'link_name' => $title,
				'link_page_id' => 0,
				'link_url' => $url,
				'link_description' => $desc,
				'link_type' => intval( $type ),
				'link_status' => 0,
				'link_submitter_user_id' => $wgUser->getID(),
				'link_submitter_user_name' => $wgUser->getName(),
				'link_submit_date' => $date
			),
			__METHOD__
		);

		// If SocialProfile extension is installed, increase social statistics.
		if ( class_exists( 'UserStatsTrack' ) ) {
			$stats = new UserStatsTrack( $wgUser->getID(), $wgUser->getName() );
			$stats->incStatField( 'links_submitted' );
		}
	}

	/**
	 * Approve a link with the given ID and perform all the related magic.
	 * This includes creating the Link: page, updating the database and updating
	 * social statistics (when SocialProfile is installed & active).
	 *
	 * @param $id Integer: link identifier
	 */
	public function approveLink( $id ) {
		$link = $this->getLink( $id );

		// Create the wiki page for the newly-approved link
		$linkTitle = Title::makeTitleSafe( NS_LINK, $link['title'] );
		$page = WikiPage::factory( $linkTitle );
		$pageContent = ContentHandler::makeContent(
			$link['url'],
			$page->getTitle()
		);
		$page->doEditContent(
			$pageContent,
			wfMessage( 'linkfilter-edit-summary' )->inContentLanguage()->text()
		);
		$newPageID = $page->getID();

		// Tie link record to wiki page
		$dbw = wfGetDB( DB_MASTER );

		wfSuppressWarnings();
		$date = date( 'Y-m-d H:i:s' );
		wfRestoreWarnings();

		$dbw->update(
			'link',
			/* SET */array(
				'link_page_id' => intval( $newPageID ),
				'link_approved_date' => $date
			),
			/* WHERE */array( 'link_id' => intval( $id ) ),
			__METHOD__
		);

		if ( class_exists( 'UserStatsTrack' ) ) {
			$stats = new UserStatsTrack( $link['user_id'], $link['user_name'] );
			$stats->incStatField( 'links_approved' );
		}
	}

	/**
	 * Gets a link entry by given page ID.
	 *
	 * @param $pageId Integer: page ID number
	 * @return array
	 */
	public function getLinkByPageID( $pageId ) {
		global $wgOut;

		if ( !is_numeric( $pageId ) ) {
			return '';
		}

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'link',
			array(
				'link_id', 'link_name', 'link_url', 'link_description',
				'link_type', 'link_status', 'link_page_id',
				'link_submitter_user_id', 'link_submitter_user_name'
			),
			array( 'link_page_id' => $pageId ),
			__METHOD__,
			array(
				'OFFSET' => 0,
				'LIMIT' => 1
			)
		);

		$row = $dbr->fetchObject( $res );

		$link = array();
		if ( $row ) {
			$link['id'] = $row->link_id;
			$link['title'] = $row->link_name;
			$link['url'] = $row->link_url;
			$link['type'] = $row->link_type;
			$link['description'] = $wgOut->parse( $row->link_description, false );
			$link['type_name'] = self::getLinkType( $row->link_type );
			$link['status'] = $row->link_status;
			$link['page_id'] = $row->link_page_id;
			$link['user_id'] = $row->link_submitter_user_id;
			$link['user_name'] = $row->link_submitter_user_name;
		}

		return $link;
	}

	static function getLinkTypes() {
		global $wgLinkFilterTypes;

		if ( is_array( $wgLinkFilterTypes ) ) {
			return $wgLinkFilterTypes;
		} else {
			return self::$link_types;
		}
	}

	/**
	 * @param $index Integer: numerical index representing the link filter type
	 * @return String: link type name or nothing
	 */
	static function getLinkType( $index ) {
		global $wgLinkFilterTypes;

		if (
			is_array( $wgLinkFilterTypes ) &&
			!empty( $wgLinkFilterTypes[$index] )
		)
		{
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
	 * @param $id Integer: link ID number
	 * @return array
	 */
	public function getLink( $id ) {
		global $wgOut;

		if ( !is_numeric( $id ) ) {
			return '';
		}

		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			'link',
			array(
				'link_id', 'link_name', 'link_url', 'link_description',
				'link_type', 'link_status', 'link_page_id',
				'link_submitter_user_id', 'link_submitter_user_name'
			),
			array( 'link_id' => $id ),
			__METHOD__,
			array(
				'OFFSET' => 0,
				'LIMIT' => 1
			)
		);

		$row = $dbr->fetchObject( $res );
		$link = array();
		if ( $row ) {
			$link['id'] = $row->link_id;
			$link['title'] = $row->link_name;
			$link['url'] = $row->link_url;
			$link['description'] = $wgOut->parse( $row->link_description, false );
			$link['type'] = $row->link_type;
			$link['type_name'] = self::getLinkType( $row->link_type );
			$link['status'] = $row->link_status;
			$link['page_id'] = $row->link_page_id;
			$link['user_id'] = $row->link_submitter_user_id;
			$link['user_name'] = $row->link_submitter_user_name;
		}

		return $link;
	}
}
