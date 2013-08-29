<?php
/**
 * A restricted special page for approving and rejecting user-submitted links.
 *
 * @file
 * @ingroup Extensions
 */
class LinkApprove extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'LinkApprove', 'linkadmin' );
	}

	/**
	 * The following four functions are borrowed
	 * from includes/wikia/GlobalFunctionsNY.php
	 */
	function dateDifference( $date1, $date2 ) {
		$dtDiff = $date1 - $date2;

		$totalDays = intval( $dtDiff / ( 24 * 60 * 60 ) );
		$totalSecs = $dtDiff - ( $totalDays * 24 * 60 * 60 );
		$dif['w'] = intval( $totalDays / 7 );
		$dif['d'] = $totalDays;
		$dif['h'] = $h = intval( $totalSecs / ( 60 * 60 ) );
		$dif['m'] = $m = intval( ( $totalSecs - ( $h * 60 * 60 ) ) / 60 );
		$dif['s'] = $totalSecs - ( $h * 60 * 60 ) - ( $m * 60 );

		return $dif;
	}

	function getLFTimeOffset( $time, $timeabrv, $timename ) {
		$timeStr = '';
		if ( $time[$timeabrv] > 0 ) {
			// Give grep a chance to find the usages:
			// linkfilter-time-days, linkfilter-time-hours,
			// linkfilter-time-minutes, linkfilter-time-seconds
			$timeStr = wfMessage( "linkfilter-time-{$timename}", $time[$timeabrv] )->parse();
		}
		if ( $timeStr ) {
			$timeStr .= ' ';
		}
		return $timeStr;
	}

	function getLFTimeAgo( $time ) {
		$timeArray = self::dateDifference( time(), $time );
		$timeStr = '';
		$timeStrD = self::getLFTimeOffset( $timeArray, 'd', 'days' );
		$timeStrH = self::getLFTimeOffset( $timeArray, 'h', 'hours' );
		$timeStrM = self::getLFTimeOffset( $timeArray, 'm', 'minutes' );
		$timeStrS = self::getLFTimeOffset( $timeArray, 's', 'seconds' );
		$timeStr = $timeStrD;
		if ( $timeStr < 2 ) {
			$timeStr .= $timeStrH;
			$timeStr .= $timeStrM;
			if ( !$timeStr ) {
				$timeStr .= $timeStrS;
			}
		}
		if ( !$timeStr ) {
			$timeStr = wfMessage( 'linkfilter-time-seconds', 1 )->parse();
		}
		return $timeStr;
	}

	/**
	 * Cuts link text if it's too long.
	 * For example, http://www.google.com/some_stuff_here could be changed into
	 * http://goo...stuff_here or so.
	 */
	public static function cutLinkFilterLinkText( $matches ) {
		$tagOpen = $matches[1];
		$linkText = $matches[2];
		$tagClose = $matches[3];

		$image = preg_match( '/<img src=/i', $linkText );
		$isURL = Link::isURL( $linkText );

		if ( $isURL && !$image && strlen( $linkText ) > 60 ) {
			$start = substr( $linkText, 0, ( 60 / 2 ) - 3 );
			$end = substr( $linkText, strlen( $linkText ) - ( 60 / 2 ) + 3, ( 60 / 2 ) - 3 );
			$linkText = trim( $start ) . wfMessage( 'ellipsis' )->plain() . trim( $end );
		}
		return $tagOpen . $linkText . $tagClose;
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Check for linkadmin permission
		if(  !$user->isAllowed( 'linkadmin' ) ) {
			throw new ErrorPageError( 'error', 'badaccess' );
			return false;
		}

		// Blocked through Special:Block? No access for you either!
		if ( $user->isBlocked() ) {
			$out->blockedPage( false );
			return false;
		}

		// Is the database locked or not?
		if ( wfReadOnly() ) {
			$out->readOnlyPage();
			return false;
		}

		// Set the page title
		$out->setPageTitle( $this->msg( 'linkfilter-approve-title' )->plain() );

		// Add CSS & JS
		$out->addModules( 'ext.linkFilter' );

		$output = '';
		$output .= '<div class="lr-left">';

		$l = new LinkList();

		$links = $l->getLinkList( LINK_OPEN_STATUS, /*$type*/0, 0, 0 );
		$links_count = count( $links );
		$x = 1;

		// The approval queue is empty? If so, show a message to the user about
		// that!
		if ( $links_count <= 0 ) {
			$out->addWikiMsg( 'linkfilter-nothing-to-approve' );
		}

		foreach ( $links as $link ) {
			$linkText = preg_replace_callback(
				'/(<a[^>]*>)(.*?)(<\/a>)/i',
				array( 'LinkApprove', 'cutLinkFilterLinkText' ),
				"<a href=\"{$link['url']}\" target=\"new\">{$link['url']}</a>"
			);

			$border_fix = '';
			if ( $links_count == $x ) {
				$border_fix = ' border-fix';
			}

			$output .= "<div class=\"admin-link{$border_fix}\">
					<div class=\"admin-title\"><b>" . $this->msg( 'linkfilter-title' )->text() .
						'</b>: ' . htmlspecialchars( $link['title'] ) .
					'</div>
					<div class="admin-desc"><b>' . $this->msg( 'linkfilter-description' )->text() .
						'</b>: ' . htmlspecialchars( $link['description'] ) .
					'</div>
					<div class="admin-url"><b>' . $this->msg( 'linkfilter-url' )->text() .
						'</b>: ' . $linkText . '</div>
					<div class="admin-submitted">' .
						$this->msg( 'linkfilter-submittedby', $link['user_name'] )->parse() .
						$this->msg( 'word-separator' )->text() .
					$this->msg(
						'linkfilter-ago',
						self::getLFTimeAgo( $link['timestamp'] ),
						Link::getLinkType( $link['type'] )
					)->parse() . "</div>
					<div id=\"action-buttons-{$link['id']}\" class=\"action-buttons\">
						<a href=\"javascript:void(0);\" class=\"action-accept\" data-link-id=\"{$link['id']}\">" .
							$this->msg( 'linkfilter-admin-accept' )->text() . "</a>
						<a href=\"javascript:void(0);\" class=\"action-reject\" data-link-id=\"{$link['id']}\">" .
							$this->msg( 'linkfilter-admin-reject' )->text() . '</a>
						<div class="cleared"></div>
					</div>';
			$output .= '</div>';

			$x++;
		}

		// Admin instructions and the column of recently approved links
		$output .= '</div>';
		$output .= '<div class="lr-right">
			<div class="admin-link-instruction">' .
				$this->msg( 'linkfilter-admin-instructions' )->inContentLanguage()->parse() .
			'</div>
			<div class="approved-link-container">
				<h3>' . $this->msg( 'linkfilter-admin-recent' )->text() . '</h3>';

		$l = new LinkList();
		$links = $l->getLinkList( LINK_APPROVED_STATUS, /*$type*/0, 10, 0, 'link_approved_date' );

		// Nothing has been approved recently? Okay...
		if ( count( $links ) <= 0 ) {
			$output .= $this->msg( 'linkfilter-no-recently-approved' )->text();
		} else { // Yay, we have something! Let's build a list!
			foreach ( $links as $link ) {
				$output .= '<div class="approved-link">
				<a href="' . $link['url'] . '" target="new">' .
					$link['title'] .
				'</a>
				<span class="approve-link-time">' .
				$this->msg(
					'linkfilter-ago',
					self::getLFTimeAgo( $link['approved_timestamp'] ),
					Link::getLinkType( $link['type'] )
				)->parse() . '</span>
			</div>';
			}
		}

		$output .= '</div>
			</div>
			<div class="cleared"></div>';

		$out->addHTML( $output );
	}
}
