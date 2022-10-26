<?php

use Wikimedia\AtEase\AtEase;

/**
 * Links' homepage -- a listing of user-submitted links.
 *
 * @file
 * @ingroup Extensions
 */
class SpecialLinksHome extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'LinksHome' );
	}

	/**
	 * Displays news items from MediaWiki:Inthenews
	 * @return string HTML
	 */
	function getInTheNews() {
		global $wgLinkPageDisplay;

		if ( !$wgLinkPageDisplay['in_the_news'] ) {
			return '';
		}

		$newsArray = explode( "\n\n", $this->msg( 'inthenews' )->inContentLanguage()->text() );
		$newsItem = $newsArray[array_rand( $newsArray )];
		$output = '<div class="link-container border-fix">
			<h2>' . $this->msg( 'linkfilter-in-the-news' )->escaped() . '</h2>
			<div>' . $this->getOutput()->parseAsContent( $newsItem, false ) . '</div>
		</div>';

		return $output;
	}

	/**
	 * Get popular link articles (Link: pages which have at least 5 comments)
	 * if that feature is enabled in site configuration.
	 *
	 * @return string
	 */
	function getPopularArticles() {
		global $wgLinkPageDisplay;

		if ( !$wgLinkPageDisplay['popular_articles'] ) {
			return '';
		}

		$dbr = wfGetDB( DB_REPLICA );

		// This query is a lot simpler than the ugly one used by BlogPage et
		// al. which uses three tables and has that nasty subquery
		$res = $dbr->select(
			[ 'link', 'page' ],
			[
				'DISTINCT link_page_id', 'page_id', 'page_title',
				'page_is_redirect'
			],
			[
				'link_comment_count >= 5',
				'link_page_id = page_id',
				'page_is_redirect' => 0
			],
			__METHOD__,
			[
				'ORDER BY' => 'page_id DESC',
				'LIMIT' => 7
			]
		);

		$popularLinks = [];
		foreach ( $res as $row ) {
			$popularLinks[] = [
				'title' => $row->page_title,
				'id' => $row->page_id
			];
		}

		$html = '<div class="listpages-container">';
		if ( empty( $popularLinks ) ) {
			$html .= $this->msg( 'linkfilter-no-results' )->escaped();
		} else {
			foreach ( $popularLinks as $popularLink ) {
				$titleObj = Title::makeTitle( NS_LINK, $popularLink['title'] );
				$html .= '<div class="listpages-item">';
				$html .= '<a href="' . htmlspecialchars( $titleObj->getFullURL() ) . '">' .
						$titleObj->getText() .
					'</a>
				</div><!-- .listpages-item -->
				<div class="visualClear"></div>' . "\n";
			}
		}

		// .listpages-container
		$html .= '</div>' . "\n";

		$output = '<div class="link-container">
			<h2>' . $this->msg( 'linkfilter-popular-articles' )->escaped() . '</h2>
			<div>' . $html . '</div>
		</div>';

		return $output;
	}

	/**
	 * Gets a wide skyscraper ad unit
	 * @return string HTML
	 */
	function getAdUnit() {
		global $wgLinkPageDisplay, $wgAdConfig;

		if ( !$wgLinkPageDisplay['left_ad'] ) {
			return '';
		}

		$output = '<div class="article-ad">
			<script type="text/javascript"><!--
			google_ad_client = "pub-' . $wgAdConfig['adsense-client'] . '";
			google_ad_slot = "' . $wgAdConfig['ad-slot'] . '";
			google_ad_width = 160;
			google_ad_height = 600;
			google_ad_format = "160x600_as";
			google_ad_type = "text";
			google_ad_channel = "";
			google_color_border = "F6F4C4";
			google_color_bg = "FFFFE0";
			google_color_link = "000000";
			google_color_text = "000000";
			google_color_url = "002BB8";
			//--></script>
			<script type="text/javascript"
			  src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
			</script>
		</div>';
		return $output;
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par Parameter passed to the page, if any [unused]
	 * @return bool|void
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();

		// Add CSS & JS
		$out->addModuleStyles( 'ext.linkFilter.styles' );
		$out->addModules( 'ext.linkFilter.scripts' );

		$per_page = $request->getInt( 'per_page', 20 );
		$page = $request->getInt( 'page', 1 );
		$link_type = $request->getInt( 'link_type' );

		if ( $link_type ) {
			$type_name = Link::$link_types[$link_type];
			$pageTitle = $this->msg( 'linkfilter-home-title', $type_name )->escaped();
		} else {
			$type_name = 'All';
			$pageTitle = $this->msg( 'linkfilter-home-title-all' )->escaped();
		}

		$out->setPageTitle( $pageTitle );

		$output = '<div class="links-home-left">' . "\n\t";
		$output .= '<div class="link-home-navigation">
		<a href="' . Link::getSubmitLinkURL() . '">' .
			$this->msg( 'linkfilter-submit-title' )->escaped() . '</a>' . "\n";

		if ( Link::canAdmin( $this->getUser() ) ) {
			$output .= "\t\t" . '<a href="' . Link::getLinkAdminURL() . '">' .
				$this->msg( 'linkfilter-approve-links' )->escaped() . '</a>' . "\n";
		}

		$output .= "\t\t" . '<div class="visualClear"></div>
		</div>' . "\n";
		$l = new LinkList();

		$total = $l->getLinkListCount( LinkStatus::APPROVED, $link_type );
		$links = $l->getLinkList( LinkStatus::APPROVED, $link_type, $per_page, $page, 'link_approved_date' );
		$linkRedirect = SpecialPage::getTitleFor( 'LinkRedirect' );
		$output .= '<div class="links-home-container">';
		$link_count = count( $links );
		$x = 1;

		// No links at all? Oh dear...show a message to the user about that!
		if ( $link_count <= 0 ) {
			$out->addWikiMsg( 'linkfilter-no-links-at-all' );
		}

		// Create RSS feed icon for special page
		$out->setSyndicated( true );

		// Make feed (RSS/Atom) if requested
		$feedType = $request->getVal( 'feed' );
		if ( $feedType != '' ) {
			return $this->makeFeed( $feedType, $links );
		}

		foreach ( $links as $link ) {
			$border_fix = '';
			if ( $link_count == $x ) {
				$border_fix = 'border-fix';
			}

			$border_fix2 = '';
			AtEase::suppressWarnings();
			// @note approved_timestamp should be a TS in the TS_UNIX format but without
			// the cast phan thinks it's *totally* not an integer...
			$date = date( 'l, F j, Y', (int)$link['approved_timestamp'] );
			// @phan-suppress-next-line PhanUndeclaredVariable Valid complaint, but I'm not sure how to fix it...
			if ( $date != $last_date ) {
				$border_fix2 = ' border-top-fix';
				$output .= '<div class="links-home-date">';
				$output .= htmlspecialchars( $date, ENT_QUOTES );
				$output .= '</div>';
				// $unix = wfTimestamp( TS_MW, $link['approved_timestamp'] );
				// $weekday = $this->getLanguage()->getWeekdayName( gmdate( 'w', $unix ) + 1 );
				// $output .= '<div class="links-home-date">' . $weekday . '</div>';
			}
			// okay, so suppressing E_NOTICEs is kinda bad practise, but... -Jack, January 21, 2010
			AtEase::restoreWarnings();
			$last_date = $date;

			$output .= "<div class=\"link-item-container{$border_fix2}\">
					<div class=\"link-item-type\">" .
						$link['type_name'] .
					'</div>
					<div class="link-item">
						<div class="link-item-url">
							<a href="' . htmlspecialchars( $linkRedirect->getFullURL( [
								'link' => 'true', 'url' => $link['url'] ] ) ) .
								'" target="new">' .
									$link['title'] .
							'</a>
						</div>
						<div class="link-item-desc">' .
							Link::parseDescription( $link['description'] ) .
						'</div>
					</div>
					<div class="link-item-page">
						<a href="' . $link['wiki_page'] . '">(' .
							$this->msg( 'linkfilter-comments', $link['comments'] )->parse() .
						')</a>
					</div>
					<div class="visualClear"></div>';
			$output .= '</div>';

			$x++;
		}

		$output .= '</div>';

		/**
		 * Build next/prev nav
		 */
		$numofpages = $total / $per_page;

		$pageLink = $this->getPageTitle();
		$linkRenderer = $this->getLinkRenderer();

		if ( $numofpages > 1 ) {
			$output .= '<div class="page-nav">';
			if ( $page > 1 ) {
				$output .= $linkRenderer->makeLink(
					$pageLink,
					$this->msg( 'linkfilter-previous' )->text(),
					[],
					[ 'page' => ( $page - 1 ) ]
				) . $this->msg( 'word-separator' )->escaped();
			}

			if ( ( $total % $per_page ) != 0 ) {
				$numofpages++;
			}
			if ( $numofpages >= 9 && $page < $total ) {
				$numofpages = 9 + $page;
			}
			if ( $numofpages > ( $total / $per_page ) ) {
				$numofpages = ( $total / $per_page ) + 1;
			}

			for ( $i = 1; $i <= $numofpages; $i++ ) {
				if ( $i == $page ) {
					$output .= ( $i . ' ' );
				} else {
					$output .= $linkRenderer->makeLink(
						$pageLink,
						(string)$i,
						[],
						[ 'page' => $i ]
					) . $this->msg( 'word-separator' )->escaped();
				}
			}

			if ( ( $total - ( $per_page * $page ) ) > 0 ) {
				$output .= $this->msg( 'word-separator' )->escaped() .
					$linkRenderer->makeLink(
						$pageLink,
						$this->msg( 'linkfilter-next' )->text(),
						[],
						[ 'page' => ( $page + 1 ) ]
					);
			}
			$output .= '</div>';
		}

		// .links-home-left
		$output .= '</div>' . "\n";

		global $wgLinkPageDisplay;
		if ( $wgLinkPageDisplay['rightcolumn'] ) {
			$output .= '<div class="links-home-right">';
			$output .= '<div class="links-home-unit-container">';
			$output .= $this->getPopularArticles();
			$output .= $this->getInTheNews();
			$output .= '</div>';
			$output .= $this->getAdUnit();
			$output .= '</div>';
		}

		$output .= '<div class="visualClear"></div>' . "\n";
		// This is 100% certified all-natural bullshit; phan just hates $link['title'] being pre-escaped
		// _but_ it also hates it being escaped closer to the output. It's a lose-lose situation for the poor developer.
		// Same thing happens in LinkFilter.hooks.php#renderLinkFilterHook and SpecialLinkApprove.php#execute too.
		// @phan-suppress-next-line SecurityCheck-XSS
		$out->addHTML( $output );
	}

	/**
	 * Create feed (RSS/Atom) from given links array
	 * Based on ProblemReports' makeFeed() function by Maciej Brencz
	 *
	 * @param string $type Feed type, RSS or Atom
	 * @param array &$links
	 * @return bool
	 */
	function makeFeed( $type, &$links ) {
		$feed = new LinkFeed(
			$this->msg( 'linkfilter-feed-title' )->parse(),
			'',
			htmlspecialchars( $this->getPageTitle()->getFullURL(), ENT_QUOTES )
		);

		$feed->outHeader();

		foreach ( $links as $link ) {
			$item = new FeedItem(
				'[' . $link['type_name'] . '] ' . $link['title'],
				str_replace( 'http://', '', $link['url'] ),
				htmlspecialchars( Title::newFromId( $link['page_id'] )->getFullURL(), ENT_QUOTES )
			);
			$feed->outItem( $item );
		}

		$feed->outFooter();

		return true;
	}
}
