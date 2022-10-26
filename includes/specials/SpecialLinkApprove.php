<?php
/**
 * A restricted special page for approving and rejecting user-submitted links.
 *
 * @file
 * @ingroup Extensions
 */
class SpecialLinkApprove extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'LinkApprove', 'linkadmin' );
	}

	/**
	 * This special page handles no-JS requests, which do indeed perform
	 * write actions. For users with JS enabled, the API modules performs the
	 * actions instead.
	 *
	 * @return bool
	 */
	public function doesWrites() {
		return true;
	}

	/**
	 * The following four functions are borrowed
	 * from includes/wikia/GlobalFunctionsNY.php
	 *
	 * @param int $date1
	 * @param int $date2
	 * @return array
	 */
	function dateDifference( $date1, $date2 ) {
		$dtDiff = $date1 - $date2;

		$totalDays = intval( $dtDiff / ( 24 * 60 * 60 ) );
		$totalSecs = $dtDiff - ( $totalDays * 24 * 60 * 60 );
		$dif = [];
		$dif['w'] = intval( $totalDays / 7 );
		$dif['d'] = $totalDays;
		$dif['h'] = $h = intval( $totalSecs / ( 60 * 60 ) );
		$dif['m'] = $m = intval( ( $totalSecs - ( $h * 60 * 60 ) ) / 60 );
		$dif['s'] = $totalSecs - ( $h * 60 * 60 ) - ( $m * 60 );

		return $dif;
	}

	/**
	 * @param array $time Time information array calculated by dateDifference()
	 * @param string $timeabrv One-letter abbreviation ('s', 'm', 'h', 'd') corresponding to $timename
	 * @param string $timename 'seconds', 'minutes', 'hours' or 'days'
	 * @return string Internationalized text suitable for output
	 */
	function getLFTimeOffset( $time, $timeabrv, $timename ) {
		$timeStr = '';
		if ( $time[$timeabrv] > 0 ) {
			// Give grep a chance to find the usages:
			// linkfilter-time-days, linkfilter-time-hours,
			// linkfilter-time-minutes, linkfilter-time-seconds
			// phpcs:ignore MediaWiki.Usage.ExtendClassUsage.FunctionVarUsage
			$timeStr = wfMessage( "linkfilter-time-{$timename}", $time[$timeabrv] )->parse();
		}
		if ( $timeStr ) {
			$timeStr .= ' ';
		}
		return $timeStr;
	}

	/**
	 * @param int $time
	 * @return string Internationalized text suitable for output
	 */
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
			// phpcs:ignore MediaWiki.Usage.ExtendClassUsage.FunctionVarUsage
			$timeStr = wfMessage( 'linkfilter-time-seconds', 1 )->parse();
		}
		return $timeStr;
	}

	/**
	 * Cuts link text if it's too long.
	 * For example, http://www.google.com/some_stuff_here could be changed into
	 * http://goo...stuff_here or so.
	 *
	 * @param array $matches
	 * @return string
	 */
	public static function cutLinkText( $matches ) {
		$tagOpen = $matches[1];
		$linkText = $matches[2];
		$tagClose = $matches[3];

		$image = preg_match( '/<img src=/i', $linkText );
		$isURL = Link::isURL( $linkText );

		if ( $isURL && !$image && strlen( $linkText ) > 60 ) {
			$start = substr( $linkText, 0, ( 60 / 2 ) - 3 );
			$end = substr( $linkText, strlen( $linkText ) - ( 60 / 2 ) + 3, ( 60 / 2 ) - 3 );
			$linkText = trim( $start ) . wfMessage( 'ellipsis' )->escaped() . trim( $end );
		}
		return $tagOpen . $linkText . $tagClose;
	}

	/**
	 * Show the special page
	 *
	 * @param mixed|null $par Parameter passed to the page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Check for linkadmin permission
		if ( !$user->isAllowed( 'linkadmin' ) ) {
			throw new ErrorPageError( 'error', 'badaccess' );
		}

		// Blocked through Special:Block? No access for you either!
		if ( $user->getBlock() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable False positive caused by core MW or something
			throw new UserBlockedError( $user->getBlock() );
		}

		// Is the database locked or not?
		$this->checkReadOnly();

		// Set the page title
		$out->setPageTitle( $this->msg( 'linkfilter-approve-title' ) );

		// Add CSS & JS
		$out->addModuleStyles( 'ext.linkFilter.styles' );
		$out->addModules( 'ext.linkFilter.scripts' );

		// Handle no-JS approval actions
		// @todo FIXME: the code inside this if() loop duplicates ApiLinkFilter code way too much
		if ( $request->wasPosted() && $user->matchEditToken( $request->getVal( 'token' ) ) ) {
			$id = $request->getInt( 'link-id' );
			$status = ( $request->getVal( 'action' ) === 'accept' ?
					LinkStatus::APPROVED : LinkStatus::REJECTED );

			$dbw = wfGetDB( DB_PRIMARY );
			$dbw->update(
				'link',
				// 1 = accept; 2 = reject
				[ 'link_status' => $status ],
				[ 'link_id' => $id ],
				__METHOD__
			);

			if ( $status == LinkStatus::APPROVED ) {
				$link = new Link();
				$link->approveLink( $id );
			}
		}

		$output = '<div class="lr-left">';

		$l = new LinkList();
		$type = $this->getRequest()->getInt( 'type', 0 );
		$links = $l->getLinkList( LinkStatus::OPEN, $type, 0, 0 );
		$links_count = count( $links );
		$x = 1;

		// The approval queue is empty? If so, show a message to the user about
		// that!
		if ( $links_count <= 0 ) {
			$out->addWikiMsg( 'linkfilter-nothing-to-approve' );
		}

		$url = htmlspecialchars( $this->getPageTitle()->getFullURL(), ENT_QUOTES );

		foreach ( $links as $link ) {
			$linkText = preg_replace_callback(
				'/(<a[^>]*>)(.*?)(<\/a>)/i',
				[ 'SpecialLinkApprove', 'cutLinkText' ],
				"<a href=\"{$link['url']}\" target=\"new\">{$link['url']}</a>"
			);

			$border_fix = '';
			if ( $links_count == $x ) {
				$border_fix = ' border-fix';
			}

			$submittedBy = User::newFromActorId( $link['actor'] )->getName();
			$id = (int)$link['id'];

			$output .= "<div class=\"admin-link{$border_fix}\">
					<div class=\"admin-title\"><b>" . $this->msg( 'linkfilter-title' )->escaped() .
						'</b>: ' . $link['title'] .
					'</div>
					<div class="admin-desc"><b>' . $this->msg( 'linkfilter-description' )->escaped() .
						'</b>: ' . $link['description'] .
					'</div>
					<div class="admin-url"><b>' . $this->msg( 'linkfilter-url' )->escaped() .
						'</b>: ' . $linkText . '</div>
					<div class="admin-submitted">' .
						$this->msg( 'linkfilter-submittedby', $submittedBy )->parse() .
						$this->msg( 'word-separator' )->escaped() .
					$this->msg(
						'linkfilter-ago',
						self::getLFTimeAgo( $link['timestamp'] ),
						Link::getLinkType( $link['type'] )
					)->parse() .
					"</div>
					<div id=\"action-buttons-{$id}\" class=\"action-buttons\">
						<form id=\"link-accept-form\" action=\"{$url}\" method=\"post\">
							<input type=\"hidden\" name=\"action\" value=\"accept\" />
							<input type=\"hidden\" name=\"link-id\" value=\"{$id}\" />
							<input type=\"hidden\" name=\"token\" value=\"" . htmlspecialchars( $user->getEditToken(), ENT_QUOTES ) . "\" />
							<input type=\"submit\" class=\"action-accept\" data-link-id=\"{$id}\" value=\"" .
								$this->msg( 'linkfilter-admin-accept' )->escaped() . "\" />
						</form>
						<form id=\"link-reject-form\" action=\"{$url}\" method=\"post\">
							<input type=\"hidden\" name=\"action\" value=\"reject\" />
							<input type=\"hidden\" name=\"link-id\" value=\"{$id}\" />
							<input type=\"hidden\" name=\"token\" value=\"" . htmlspecialchars( $user->getEditToken(), ENT_QUOTES ) . "\" />
							<input type=\"submit\" class=\"action-reject\" data-link-id=\"{$id}\" value=\"" .
								$this->msg( 'linkfilter-admin-reject' )->escaped() . '" />
						</form>
						<div class="visualClear"></div>
					</div>';
			$output .= '</div>';

			$x++;
		}

		// Admin instructions and the column of recently approved links
		$output .= '</div>';
		$output .= '<div class="lr-right">';
		// Link category filter
		$output .= '<div class="admin-link-type-filter-container">';
		$output .= $this->msg( 'linkfilter-admin-cat-filter' )->escaped();
		$output .= '<form method="get" action="' . htmlspecialchars( $this->getPageTitle()->getFullURL(), ENT_QUOTES ) . '">';
		$output .= '<select id="admin-link-type-filter" name="type">';
		// @note Intentionally _not_ using array_merge() but rather the plus operator.
		// Using array_merge() results in Link::getLinkTypes() keys being reordered
		// so that all the categories after "Funny" (ID #4) are given the wrong ID.
		// For whatever reason Link::getLinkTypes() skips over ID #5 and we must also
		// do the same and avoid reintroducing that ID, despite that we want to add
		// "All" as ID #0.
		$linkTypes = [ 0 => $this->msg( 'linkfilter-all' )->text() ] + Link::getLinkTypes();
		foreach ( $linkTypes as $id => $linkType ) {
			$output .= Xml::option( $linkType, $id, ( $id === $type ) );
		}
		$output .= '</select>';
		$output .= '<input type="submit" class="no-js-btn site-button" value="' . $this->msg( 'linkfilter-submit' )->escaped() . '" />';
		$output .= '</form>';
		$output .= '</div>';
		// Admin instructions
		$output .= '<div class="admin-link-instruction">' .
				$this->msg( 'linkfilter-admin-instructions' )->inContentLanguage()->parse() .
			'</div>';
		// A listing of recently approved links (in this category, if a category was
		// specified; otherwise shows all queued links, which is the default)
		$output .= '<div class="approved-link-container">
				<h3>' . $this->msg( 'linkfilter-admin-recent' )->escaped() . '</h3>';

		$links = $l->getLinkList( LinkStatus::APPROVED, $type, 10, 0, 'link_approved_date' );

		// Nothing has been approved recently? Okay...
		if ( count( $links ) <= 0 ) {
			$output .= $this->msg( 'linkfilter-no-recently-approved' )->escaped();
		} else {
			// Yay, we have something! Let's build a list!
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
			<div class="visualClear"></div>';

		// This is 100% certified all-natural bullshit; phan just hates $link['title'] being pre-escaped
		// _but_ it also hates it being escaped closer to the output. It's a lose-lose situation for the poor developer.
		// Same thing happens in LinkFilter.hooks.php#renderLinkFilterHook and SpecialLinksHome.php#execute too.
		// @phan-suppress-next-line SecurityCheck-XSS
		$out->addHTML( $output );
	}
}
