<?php
/**
 * Internationalization file for the LinkFilter extension.
 *
 * @file
 * @ingroup Extensions
 */

$messages = array();

/** English
 * @author Aaron Wright <aaron.wright@gmail.com>
 * @author David Pean <david.pean@gmail.com>
 */
$messages['en'] = array(
	'linkapprove' => 'Approve links',
	'linkshome' => 'Links home',
	'linksubmit' => 'Submit a link',
	'linkfilter-desc' => 'Adds new special pages and a parser hook for link submitting/approval/reject',
	'linkfilter-nothing-to-approve' => 'There are currently no links awaiting approval.',
	'linkfilter-no-recently-approved' => 'No links have been approved recently.',
	'linkfilter-no-links-at-all' => 'No links have been submitted yet or the link administrators have not reviewed the submitted links yet.',
	'linkfilter-ago' => '$1 ago under <i>$2</i>',
	'linkfilter-all' => 'All',
	'linkfilter-submit' => 'Submit',
	'linkfilter-submit-title' => 'Submit a link',
	'linkfilter-submit-no-title' => 'Please enter a title',
	'linkfilter-submit-no-type' => 'Pick a link type.',
	'linkfilter-edit-title' => 'Edit $1',
	'linkfilter-approve-links' => 'Approve links',
	'linkfilter-submit-another' => 'Submit another link',
	'linkfilter-login-title' => 'Not logged in',
	'linkfilter-login-text' => 'You must be logged in to submit links.',
	'linkfilter-url' => 'URL',
	'linkfilter-title' => 'Title',
	'linkfilter-type' => 'Link type',
	'linkfilter-description' => 'Description',
	'linkfilter-submit-button' => 'Submit link',
	'linkfilter-home-button' => 'Links home',
	'linkfilter-submit-success-title' => 'Link submitted',
	'linkfilter-submit-success-text' => 'Your link has been sent for approval',
	'linkfilter-instructions' => 'You can add some instructions for users [[MediaWiki:Linkfilter-instructions|here]]',
	'linkfilter-admin-instructions' => 'You can add some instructions for admins [[MediaWiki:Linkfilter-admin-instructions|here]]',
/**
	'linkfilter-instructions' => "==Ground Rules==
1) If it's not safe for work, label it as \"NSFW\".

2) Don't post the same link twice.

3) No links to hate sites or private information.

4) Make sure the links actually work, mmmkay?

5) In general, use common sense.

6) If you get banned for breaking these rules, don't whine about it.  The ban may only be for a day or two, but even if it's longer, stfu.

__NOTOC__
__NOEDITSECTION__",
	'linkfilter-admin-instructions' => "==Before you approve a link, remember the RULES==\n
1) No porn. <br />
2) No graphic content unless there's an NSFW warning. <br />
3) Don't post hate speech. <br />
4) Don't post broken images/links. <br />
5) Don't post private/contact information no matter how easily obtained.  <br />
6) Don't post the same link more than once. <br />
7) If some dude is spamming, don't post his links.  One is OK.<br />
8) If someone is submitting really good stuff, let me me know -- s/he'd probably make a good admin.<br />",
	*/
	'linkfilter-admin-recent' => 'Recently approved',
	'linkfilter-approve-title' => 'Link administration',
	'linkfilter-submittedby' => 'Submitted by',
	'linkfilter-submitted' => 'Submitted $1',
	'linkfilter-admin-accept' => 'Accept',
	'linkfilter-admin-reject' => 'Reject',
	'linkfilter-admin-reject-success' => 'The link was rejected',
	'linkfilter-admin-accept-success' => 'The link was accepted',
	'linkfilter-in-the-news' => 'In the news',
	'linkfilter-about-submitter' => 'About the submitter',
	'linkfilter-anonymous' => 'Anonymous fanatic',
	'linkfilter-comments-of-day' => 'Top comments',
	'linkfilter-comments' => '{{PLURAL:$1|$1 comment|$1 comments}}',
	'linkfilter-home-title' => '$1 links',
	'linkfilter-home-title-all' => 'All kinks',
	'linkfilter-next' => 'next',
	'linkfilter-previous' => 'previous',
	'linkfilter-description-max' => 'Maximum characters',
	'linkfilter-description-left' => '$1 left',
	'linkfilter-popular-articles' => 'Do not miss',
	'linkfilter-new-links-title' => 'New links',
	'linkfilter-time-days' => '{{PLURAL:$1|one day|$1 days}}',
	'linkfilter-time-hours' => '{{PLURAL:$1|one hour|$1 hours}}',
	'linkfilter-time-minutes' => '{{PLURAL:$1|one minute|$1 minutes}}',
	'linkfilter-time-seconds' => '{{PLURAL:$1|one second|$1 seconds}}',
	'linkfilter-edit-summary' => 'new link',
	'linkfilter-no-results' => 'No pages found.',
	'linkfilter-feed-title' => '{{SITENAME}} links', // RSS feed title
	// For Special:ListUsers - new group
	'group-linkadmin' => 'Link administrators',
	'group-linkadmin-member' => 'link administrator',
	'grouppage-linkadmin' => '{{ns:project}}:Link administrators',
	// For Special:ListGroupRights
	'right-linkadmin' => 'Administrate user-submitted links',
);

/** Finnish (Suomi)
 * @author Jack Phoenix <jack@countervandalism.net>
 */
$messages['fi'] = array(
	'linkapprove' => 'Linkkien ylläpito',
	'linkshome' => 'Linkkien kotisivu',
	'linksubmit' => 'Lähetä linkki',
	'linkfilter-nothing-to-approve' => 'Tällä hetkellä ei ole yhtään linkkiä odottamassa hyväksyntää.',
	'linkfilter-ago' => '$1 sitten luokkaan "<i>$2</i>"',
	'linkfilter-all' => 'Kaikki',
	'linkfilter-submit' => 'Lähetä',
	'linkfilter-submit-title' => 'Lähetä linkki',
	'linkfilter-submit-no-title' => 'Ole hyvä ja anna otsikko',
	'linkfilter-submit-no-type' => 'Hei, valitse linkin tyyppi!',
	'linkfilter-edit-title' => 'Muokkaa $1',
	'linkfilter-approve-links' => 'Hyväksy linkkejä',
	'linkfilter-submit-another' => 'Lähetä toinen linkki',
	'linkfilter-login-title' => 'Et ole kirjautunut sisään',
	'linkfilter-login-text' => 'Sinun tulee olla kirjautuneena sisään voidaksesi lähettää linkkejä.',
	'linkfilter-url' => 'URL',
	'linkfilter-title' => 'Otsikko',
	'linkfilter-type' => 'Linkin tyyppi',
	'linkfilter-description' => 'Kuvaus',
	'linkfilter-submit-button' => 'Lähetä linkki',
	'linkfilter-home-button' => 'Linkkien kotisivu',
	'linkfilter-submit-success-title' => 'Linkki lähetetty',
	'linkfilter-submit-success-text' => 'Linkkisi on lähetetty hyväksyntää odottamaan',
	'linkfilter-admin-recent' => 'Äskettäin hyväksytyt',
	'linkfilter-approve-title' => 'Linkkien ylläpito',
	'linkfilter-submittedby' => 'Lähettänyt',
	'linkfilter-submitted' => 'Lähetetty $1',
	'linkfilter-instructions' => 'Voit lisätä ohjeita käyttäjille [[MediaWiki:Linkfilter-instructions|täällä]]',
	'linkfilter-admin-instructions' => 'Voit lisätä ohjeita ylläpitäjille [[MediaWiki:Linkfilter-admin-instructions|täällä]]',
	'linkfilter-admin-accept' => 'Hyväksy',
	'linkfilter-admin-reject' => 'Hylkää',
	'linkfilter-admin-reject-success' => 'Linkki hylättiin',
	'linkfilter-admin-accept-success' => 'Linkki hyväksyttiin',
	'linkfilter-in-the-news' => 'Uutisissa',
	'linkfilter-about-submitter' => 'Tietoja lähettäjästä',
	'linkfilter-anonymous' => 'Anonyymi fanaatikko',
	'linkfilter-comments-of-day' => 'Huippukommentit',
	'linkfilter-comments' => '{{PLURAL:$1|yksi kommentti|$1 kommenttia}}',
	'linkfilter-home-title' => 'Linkit aiheesta $1',
	'linkfilter-home-title-all' => 'Kaikki linkit',
	'linkfilter-next' => 'seuraava',
	'linkfilter-previous' => 'edellinen',
	'linkfilter-description-max' => 'Merkkien maksimimäärä',
	'linkfilter-description-left' => '$1 jäljellä',
	'linkfilter-popular-articles' => 'Älä unohda',
	'linkfilter-new-links-title' => 'Uudet linkit',
	'linkfilter-time-days' => '{{PLURAL:$1|yksi päivä|$1 päivää}}',
	'linkfilter-time-hours' => '{{PLURAL:$1|yksi tunti|$1 tuntia}}',
	'linkfilter-time-minutes' => '{{PLURAL:$1|yksi minuutti|$1 minuuttia}}',
	'linkfilter-time-seconds' => '{{PLURAL:$1|yksi sekunti|$1 sekuntia}}',
	'linkfilter-edit-summary' => 'uusi linkki',
	'linkfilter-no-results' => 'Sivuja ei löytynyt.',
	'linkfilter-feed-title' => '{{GRAMMAR:genitive|{{SITENAME}}}} linkit',
	'group-linkadmin' => 'Linkkien ylläpitäjät',
	'group-linkadmin-member' => 'Linkkien ylläpitäjä',
	'right-linkadmin' => 'Hallinnoida käyttäjien lähettämiä linkkejä',
);
