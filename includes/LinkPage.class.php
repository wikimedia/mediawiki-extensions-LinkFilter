<?php
/**
 * Custom class for handling the viewing of pages in the NS_LINK namespace.
 *
 * @file
 */
class LinkPage extends Article {

	/**
	 * @var Title
	 */
	public $title = null;

	/**
	 * @var String: page content retrieved via getContent() in setContent()
	 */
	public $pageContent;

	/**
	 * @var Link: Link object representing the current link
	 */
	public $link;

	function __construct( Title $title ) {
		parent::__construct( $title );
		$this->setContent();
		$l = new Link();
		$this->link = $l->getLinkByPageID( $title->getArticleID() );
	}

	function setContent() {
		// Get the page content for later use
		$this->pageContent = ContentHandler::getContentText( $this->getContentObject() );

		// If its a redirect, in order to get the *real* content for later use,
		// we have to load the text for the real page
		// Note: If $this->getContent is called anywhere before parent::view,
		// the real article text won't get loaded on the page
		if ( $this->isRedirect() ) {
			wfDebugLog( 'LinkFilter', __METHOD__ . "\n" );

			$target = $this->followRedirect();
			$page = WikiPage::factory( $target );
			$this->pageContent = ContentHandler::getContentText( $page->getContent() );

			// if we don't clear, the page content will be [[redirect-blah]],
			// and not actual page
			$this->clear();
		}
	}

	function view() {
		global $wgOut, $wgLinkPageDisplay;

		$sk = $wgOut->getSkin();

		$wgOut->setHTMLTitle( $this->getTitle()->getText() );
		$wgOut->setPageTitle( $this->getTitle()->getText() );

		$wgOut->addHTML( '<div id="link-page-container" class="clearfix">' );

		if ( $wgLinkPageDisplay['leftcolumn'] == true ) {
			$wgOut->addHTML( '<div id="link-page-left">' );
			$wgOut->addHTML( '<div class="link-left-units">' );
			$wgOut->addHTML( $this->displaySubmitterBox() );
			$wgOut->addHTML( '</div>' );
			$wgOut->addHTML( $this->leftAdUnit() );
			$wgOut->addHTML( '</div>' );
		}

		$wgOut->addHTML( '<div id="link-page-middle">' );

		$wgOut->addHTML( $this->displayLink() );

		// Get categories
		$cat = $sk->getCategoryLinks();
		if ( $cat ) {
			$wgOut->addHTML( "<div id=\"categories\">{$cat}</div>" );
		}

		if ( ExtensionRegistry::getInstance()->isLoaded( 'Comments' ) ) {
			$wgOut->addWikiTextAsInterface( '<comments/>' );
		}

		$wgOut->addHTML( '</div>' );

		if ( $wgLinkPageDisplay['rightcolumn'] == true ) {
			$wgOut->addHTML( '<div id="link-page-right">' );

			$wgOut->addHTML( $this->getNewLinks() );
			$wgOut->addHTML( $this->getInTheNews() );
			$wgOut->addHTML( $this->getCommentsOfTheDay() );
			$wgOut->addHTML( $this->getRandomCasualGame() );

			$wgOut->addHTML( '</div>' );
		}
		$wgOut->addHTML( '<div class="visualClear"></div>' );
		$wgOut->addHTML( '</div>' );

	}

	function displayLink() {
		global $wgLang;

		$url = '';
		$domain = '';
		if ( Link::isURL( $this->link['url'] ) ) {
			$url = parse_url( $this->link['url'] );
			$domain = $url['host'];
		}

		$create_date = $wgLang->timeanddate(
			$this->getCreateDate( $this->getTitle()->getArticleID() ),
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
						. $this->link['type_name'] .
					'</span>
					<a href="' . $url . "\" target=\"new\">
						{$this->link['title']}
					</a>
				</div>
				<div class=\"link-date\">(" .
					wfMessage( 'linkfilter-submitted', $create_date )->parse() . ")</div>
				<div class=\"link-description\">
					{$this->link['description']}
				</div>
				<div class=\"link-domain\">{$domain}</div>
			</div>";

		return $output;
	}

	/**
	 * Get the creation date of the page with ID = $pageId, either from
	 * memcached or if that fails, then from the database.
	 *
	 * @param int $pageId Page ID number
	 * @return int Page creation date as a UNIX timestamp
	 */
	function getCreateDate( $pageId ) {
		global $wgMemc;

		$key = $wgMemc->makeKey( 'page', 'create_date', $pageId );
		$data = $wgMemc->get( $key );

		if ( !$data ) {
			$dbr = wfGetDB( DB_MASTER );
			$createDate = $dbr->selectField(
				'revision',
				'rev_timestamp',
				[ 'rev_page' => intval( $pageId ) ],
				__METHOD__,
				[ 'ORDER BY' => 'rev_timestamp ASC' ]
			);
			$wgMemc->set( $key, $createDate, 7 * 86400 );
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
		global $wgOut, $wgLinkPageDisplay;

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

		$css_fix = 'author-container-fix';
		$output = '<h2>' . wfMessage( 'linkfilter-about-submitter' )->text() . '</h2>';
		$output .= "<div class=\"author-container $css_fix\">
			<div class=\"author-info\">
				<a href=\"" . htmlspecialchars( $authorTitle->getFullURL(), ENT_QUOTES ) . "\" rel=\"nofollow\">
					{$avatar->getAvatarURL()}
				</a>
				<div class=\"author-title\">
					<a href=\"" . htmlspecialchars( $authorTitle->getFullURL(), ENT_QUOTES ) . "\" rel=\"nofollow\">{$authorUserName}</a>
				</div>";
		if ( $profileData['about'] ) {
			$output .= $wgOut->parseAsContent( $profileData['about'], false );
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
		global $wgLinkPageDisplay, $wgOut;

		if ( !$wgLinkPageDisplay['in_the_news'] ) {
			return '';
		}

		$newsArray = explode( "\n\n", wfMessage( 'inthenews' )->inContentLanguage()->text() );
		$newsItem = $newsArray[array_rand( $newsArray )];
		$output = '<div class="link-container">
			<h2>' . wfMessage( 'linkfilter-in-the-news' )->text() . '</h2>
			<div>' . $wgOut->parseAsContent( $newsItem, false ) . '</div>
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
		$links = $l->getLinkList( LinkStatus::APPROVED, '', 7, 0 );

		foreach ( $links as $link ) {
			$output .= '<div class="link-recent">
			<a href="' . htmlspecialchars( $linkRedirect->getFullURL( "url={$link['url']}" ), ENT_QUOTES ) .
				"\" target=\"new\">{$link['title']}</a>
		</div>";
		}

		$output = '<div class="link-container">
			<h2>' . wfMessage( 'linkfilter-new-links-title' )->text() . '</h2>
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
		global $wgLinkPageDisplay, $wgMemc;

		if ( !$wgLinkPageDisplay['comments_of_day'] ) {
			return '';
		}

		$comments = [];

		// Try cache first
		$key = $wgMemc->makeKey( 'comments-link', 'plus', '24hours' );
		$wgMemc->delete( $key );
		$data = $wgMemc->get( $key );

		if ( $data != '' ) {
			wfDebugLog( 'LinkFilter', "Got comments of the day from cache\n" );
			$comments = $data;
		} else {
			wfDebugLog( 'LinkFilter', "Got comments of the day from DB\n" );

			$dbr = wfGetDB( DB_MASTER );
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
			$wgMemc->set( $key, $comments, 60 * 15 );
		}

		$output = '';

		foreach ( $comments as $comment ) {
			$pageTitle = Title::makeTitle( $comment['namespace'], $comment['title'] );

			$actor = User::newFromActorId( $comment['actor'] );
			if ( !$actor || !$actor instanceof User ) {
				continue;
			}

			if ( $actor->isAnon() ) {
				$commentPosterDisplay = wfMessage( 'linkfilter-anonymous' )->text();
			} else {
				$commentPosterDisplay = $actor->getName();
			}

			$commentText = substr( $comment['comment_text'], 0, 70 - strlen( $commentPosterDisplay ) );
			if ( $commentText != $comment['comment_text'] ) {
				$commentText .= wfMessage( 'ellipsis' )->plain();
			}
			$output .= '<div class="cod-item">';
			$output .= '<span class="cod-score">' . $comment['plus_count'] . '</span> ';
			$url = htmlspecialchars( $pageTitle->getFullURL(), ENT_QUOTES );
			$output .= " <span class=\"cod-comment\"><a href=\"{$url}#comment-{$comment['comment_id']}\" title=\"{$pageTitle->getText()}\" >{$commentText}</a></span>";
			$output .= '</div>';
		}

		if ( count( $comments ) > 0 ) {
			$output = '<div class="link-container">
				<h2>' . wfMessage( 'linkfilter-comments-of-day' )->text() . '</h2>' .
				$output .
			'</div>';
		}

		return $output;
	}
}
