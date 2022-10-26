<?php
/**
 * Custom class for handling the viewing of pages in the NS_LINK namespace.
 *
 * @file
 */

use MediaWiki\MediaWikiServices;

class LinkPage extends Article {

	/**
	 * @var Title
	 */
	public $title = null;

	/**
	 * @var array Link information about the current link gotten via Link#getLinkByPageID
	 */
	public $link;

	/**
	 * @param Title $title
	 */
	function __construct( Title $title ) {
		parent::__construct( $title );
		$l = new Link();
		$this->link = $l->getLinkByPageID( $title->getArticleID() );
	}

	/**
	 * @suppress SecurityCheck-XSS Phan likes to complain essentially about $link['title'] in
	 *   getNewLinks(), but it has been pre-escaped already, but adding the suppression to that
	 *   method (whether method-level, like here, or using the "suppress previous/next line" syntax)
	 *   just doesn't work. :-(
	 */
	function view() {
		global $wgLinkPageDisplay;

		$context = $this->getContext();
		$out = $context->getOutput();
		$user = $context->getUser();

		$sk = $out->getSkin();

		$out->setHTMLTitle( $this->getTitle()->getText() );
		$out->setPageTitle( $this->getTitle()->getText() );

		$out->addHTML( '<div id="link-page-container" class="clearfix">' );

		if ( $wgLinkPageDisplay['leftcolumn'] == true ) {
			$out->addHTML( '<div id="link-page-left">' );
			$out->addHTML( '<div class="link-left-units">' );
			$out->addHTML( $this->displaySubmitterBox() );
			$out->addHTML( '</div>' );
			$out->addHTML( $this->leftAdUnit() );
			$out->addHTML( '</div>' );
		}

		$out->addHTML( '<div id="link-page-middle">' );

		$out->addHTML( $this->displayLink() );

		// Get categories
		$cat = $sk->getCategoryLinks();
		if ( $cat ) {
			$out->addHTML( "<div id=\"categories\">{$cat}</div>" );
		}

		if ( ExtensionRegistry::getInstance()->isLoaded( 'Comments' ) ) {
			$out->addWikiTextAsInterface( '<comments/>' );
		}

		$out->addHTML( '</div>' );

		if ( $wgLinkPageDisplay['rightcolumn'] == true ) {
			$out->addHTML( '<div id="link-page-right">' );

			$out->addHTML( $this->getNewLinks() );
			$out->addHTML( $this->getInTheNews() );
			$out->addHTML( $this->getCommentsOfTheDay() );
			$out->addHTML( $this->getRandomCasualGame() );

			$out->addHTML( '</div>' );
		}

		$out->addHTML( '<div class="visualClear"></div>' );
		$out->addHTML( '</div>' );
	}

	function displayLink() {
		$url = '';
		$domain = '';
		if ( Link::isURL( $this->link['url'] ) ) {
			$url = parse_url( $this->link['url'] );
			// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
			$domain = $url['host'];
		}

		$create_date = $this->getContext()->getLanguage()->timeanddate(
			(string)$this->getCreateDate( $this->getTitle()->getArticleID() ),
			true
		);
		$linkRedirect = SpecialPage::getTitleFor( 'LinkRedirect' );
		$url = htmlspecialchars( $linkRedirect->getFullURL( [
			'link' => 'true',
			'url' => $this->link['url']
		] ), ENT_QUOTES );
		$output = '<div class="link-container">
				<div class="link-url">
					<span class="link-type">'
						. htmlspecialchars( $this->link['type_name'], ENT_QUOTES ) .
					'</span>
					<a href="' . $url . '" target="new">' .
						htmlspecialchars( $this->link['title'], ENT_QUOTES ) .
					'</a>
				</div>
				<div class="link-date">(' .
					wfMessage( 'linkfilter-submitted', $create_date )->parse() . ')</div>
				<div class="link-description">' .
					Link::parseDescription( $this->link['description'] ) .
				'</div>
				<div class="link-domain">' . htmlspecialchars( $domain, ENT_QUOTES ) . '</div>
			</div>';

		return $output;
	}

	/**
	 * Get the creation date of the page with ID = $pageId, either from
	 * cache or if that fails, then from the database.
	 *
	 * @param int $pageId Page ID number
	 * @return int Page creation date as a UNIX timestamp
	 */
	function getCreateDate( $pageId ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $cache->makeKey( 'page', 'create_date', $pageId );
		$data = $cache->get( $key );

		if ( !$data ) {
			$dbr = wfGetDB( DB_PRIMARY );
			$createDate = $dbr->selectField(
				'revision',
				'rev_timestamp',
				[ 'rev_page' => intval( $pageId ) ],
				__METHOD__,
				[ 'ORDER BY' => 'rev_timestamp ASC' ]
			);
			$cache->set( $key, $createDate, 7 * 86400 );
		} else {
			wfDebugLog( 'LinkFilter', "Loading create_date for page {$pageId} from cache\n" );
			$createDate = $data;
		}

		return $createDate;
	}

	/**
	 * Displays the box which displays information about the person who
	 * submitted this link if this feature is enabled in $wgLinkPageDisplay.
	 *
	 * @return string HTML
	 */
	function displaySubmitterBox() {
		global $wgLinkPageDisplay;

		if ( !$wgLinkPageDisplay['author'] ) {
			return '';
		}

		$author = User::newFromActorId( $this->link['actor'] );
		$authorUserId = $author->getId();
		$authorUserName = $author->getName();

		if ( !$authorUserId ) {
			return '';
		}

		$authorTitle = Title::makeTitle( NS_USER, $authorUserName );

		$profile = new UserProfile( $authorUserName );
		$profileData = $profile->getProfile();

		$avatar = new wAvatar( $authorUserId, 'm' );

		$safeAuthorUserName = htmlspecialchars( $author->getName(), ENT_QUOTES );

		$css_fix = 'author-container-fix';
		$output = '<h2>' . wfMessage( 'linkfilter-about-submitter' )->escaped() . '</h2>';
		$output .= "<div class=\"author-container $css_fix\">
			<div class=\"author-info\">
				<a href=\"" . htmlspecialchars( $authorTitle->getFullURL(), ENT_QUOTES ) . "\" rel=\"nofollow\">
					{$avatar->getAvatarURL()}
				</a>
				<div class=\"author-title\">
					<a href=\"" . htmlspecialchars( $authorTitle->getFullURL(), ENT_QUOTES ) . "\" rel=\"nofollow\">{$safeAuthorUserName}</a>
				</div>";
		if ( $profileData['about'] ) {
			$output .= $this->getContext()->getOutput()->parseAsContent( $profileData['about'], false );
		}
		$output .= '</div>
			<div class="visualClear"></div>
		</div>';

		return $output;
	}

	/**
	 * Gets a wide skyscraper ad unit, if this feature is enabled in
	 * $wgLinkPageDisplay.
	 *
	 * @return string HTML
	 */
	function leftAdUnit() {
		global $wgLinkPageDisplay;

		if ( !$wgLinkPageDisplay['left_ad'] ) {
			return '';
		}

		global $wgAdConfig;
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
	 * Displays news items from MediaWiki:Inthenews if this feature is enabled
	 * in $wgLinkPageDisplay.
	 *
	 * @return string HTML
	 */
	function getInTheNews() {
		global $wgLinkPageDisplay;

		if ( !$wgLinkPageDisplay['in_the_news'] ) {
			return '';
		}

		$context = $this->getContext();
		$newsArray = explode( "\n\n", $context->msg( 'inthenews' )->inContentLanguage()->text() );
		$newsItem = $newsArray[array_rand( $newsArray )];
		$output = '<div class="link-container">
			<h2>' . $context->msg( 'linkfilter-in-the-news' )->escaped() . '</h2>
			<div>' . $context->getOutput()->parseAsContent( $newsItem, false ) . '</div>
		</div>';

		return $output;
	}

	/**
	 * Displays a list of recently approved links if this feature is enabled in
	 * $wgLinkPageDisplay.
	 *
	 * @return string HTML
	 */
	function getNewLinks() {
		global $wgLinkPageDisplay;

		if ( !$wgLinkPageDisplay['new_links'] ) {
			return '';
		}

		$output = '';

		$linkRedirect = SpecialPage::getTitleFor( 'LinkRedirect' );
		$l = new LinkList();
		$links = $l->getLinkList( LinkStatus::APPROVED, 0, 7, 0 );

		foreach ( $links as $link ) {
			$output .= '<div class="link-recent">
			<a href="' . htmlspecialchars( $linkRedirect->getFullURL( "url={$link['url']}" ), ENT_QUOTES ) . '" target="new">' .
				$link['title'] . '</a>
		</div>';
		}

		$output = '<div class="link-container">
			<h2>' . $this->getContext()->msg( 'linkfilter-new-links-title' )->escaped() . '</h2>
			<div>' . $output . '</div>
		</div>';

		return $output;
	}

	/**
	 * Gets a random casual game if RandomGameUnit extension is installed and
	 * this feature is enabled in $wgLinkPageDisplay.
	 *
	 * @return string HTML or nothing
	 */
	function getRandomCasualGame() {
		global $wgLinkPageDisplay;

		if ( !$wgLinkPageDisplay['games'] ) {
			return '';
		}

		if ( ExtensionRegistry::getInstance()->isLoaded( 'RandomGameUnit' ) ) {
			$this->getContext()->getOutput()->addModuleStyles( 'ext.RandomGameUnit.css' );
			return RandomGameUnit::getRandomGameUnit();
		} else {
			return '';
		}
	}

	/**
	 * Gets the comments of the day if this feature is enabled in
	 * $wgLinkPageDisplay.
	 *
	 * @return string HTML
	 */
	function getCommentsOfTheDay() {
		global $wgLinkPageDisplay;

		if ( !$wgLinkPageDisplay['comments_of_day'] ) {
			return '';
		}

		$comments = [];

		// Try cache first
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $cache->makeKey( 'comments-link', 'plus', '24hours' );
		$cache->delete( $key );
		$data = $cache->get( $key );

		if ( $data != '' ) {
			wfDebugLog( 'LinkFilter', "Got comments of the day from cache\n" );
			$comments = $data;
		} else {
			wfDebugLog( 'LinkFilter', "Got comments of the day from DB\n" );

			$dbr = wfGetDB( DB_PRIMARY );
			$res = $dbr->select(
				[ 'Comments', 'page' ],
				[
					'Comment_actor', 'comment_ip', 'comment_text',
					'comment_date', 'CommentID',
					'IFNULL(Comment_Plus_Count - Comment_Minus_Count,0) AS Comment_Score',
					'Comment_Plus_Count AS CommentVotePlus',
					'Comment_Minus_Count AS CommentVoteMinus',
					'Comment_Parent_ID', 'page_title', 'page_namespace'
				],
				[
					'comment_page_id = page_id',
					'UNIX_TIMESTAMP(comment_date) > ' . ( time() - ( 60 * 60 * 24 ) ),
					'page_namespace = ' . NS_LINK
				],
				__METHOD__,
				[
					'ORDER BY' => '(Comment_Plus_Count) DESC',
					'OFFSET' => 0,
					'LIMIT' => 5
				]
			);

			foreach ( $res as $row ) {
				$comments[] = [
					'actor' => $row->Comment_actor,
					'title' => $row->page_title,
					'namespace' => $row->page_namespace,
					'comment_id' => $row->CommentID,
					'plus_count' => $row->CommentVotePlus,
					'comment_text' => $row->comment_text
				];
			}

			$cache->set( $key, $comments, 60 * 15 );
		}

		$output = '';

		foreach ( $comments as $comment ) {
			$pageTitle = Title::makeTitle( $comment['namespace'], $comment['title'] );

			$actor = User::newFromActorId( $comment['actor'] );
			if ( !$actor || !$actor instanceof User ) {
				continue;
			}

			if ( $actor->isAnon() ) {
				$commentPosterDisplay = wfMessage( 'linkfilter-anonymous' )->escaped();
			} else {
				$commentPosterDisplay = $actor->getName();
			}

			$commentText = substr( $comment['comment_text'], 0, 70 - strlen( $commentPosterDisplay ) );
			if ( $commentText != $comment['comment_text'] ) {
				$commentText .= wfMessage( 'ellipsis' )->plain();
			}
			$output .= '<div class="cod-item">';
			$output .= '<span class="cod-score">' . (int)$comment['plus_count'] . '</span> ';
			$url = htmlspecialchars( $pageTitle->getFullURL(), ENT_QUOTES );
			$output .= " <span class=\"cod-comment\">";
			$output .= '<a href="' . $url . '#comment-' . (int)$comment['comment_id'] . '" title="' . htmlspecialchars( $pageTitle->getText(), ENT_QUOTES ) . '">';
			$output .= htmlspecialchars( $commentText, ENT_QUOTES );
			$output .= '</a></span>';
			$output .= '</div>';
		}

		if ( count( $comments ) > 0 ) {
			$output = '<div class="link-container">
				<h2>' . wfMessage( 'linkfilter-comments-of-day' )->escaped() . '</h2>' .
				$output .
			'</div>';
		}

		return $output;
	}
}
