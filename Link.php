<?php
/**
 * LinkFilter extension
 * Adds some new special pages and a parser hook for link submitting/approval/reject
 *
 * @file
 * @ingroup Extensions
 * @author Aaron Wright <aaron.wright@gmail.com>
 * @author David Pean <david.pean@gmail.com>
 * @author Jack Phoenix <jack@countervandalism.net>
 * @link https://www.mediawiki.org/wiki/Extension:LinkFilter Documentation
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This is not a valid entry point.\n" );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'LinkFilter',
	'version' => '3.1.0',
	'author' => array( 'Aaron Wright', 'David Pean', 'Jack Phoenix' ),
	'descriptionmsg' => 'linkfilter-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:LinkFilter'
);

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.linkFilter'] = array(
	'styles' => 'LinkFilter.css',
	'scripts' => 'LinkFilter.js',
	'messages' => array(
		'linkfilter-admin-accept-success', 'linkfilter-admin-reject-success',
		'linkfilter-submit-no-title', 'linkfilter-submit-no-type'
	),
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => 'LinkFilter',
	'position' => 'top' // available since r85616
);

// Define some constants (namespaces + some other stuff)
define( 'NS_LINK', 700 );
define( 'NS_LINK_TALK', 701 );

define( 'LINK_APPROVED_STATUS', 1 );
define( 'LINK_OPEN_STATUS', 0 );
define( 'LINK_REJECTED_STATUS', 2 );

// Array of LinkFilter types
// Key is: number => 'description'
// For example: 2 => 'Awesome',
$wgLinkFilterTypes = array(
	1 => 'Arrest Report',
	2 => 'Awesome',
	3 => 'Cool',
	4 => 'Funny',
	6 => 'Interesting',
	7 => 'Obvious',
	8 => 'OMG WTF?!?',
	9 => 'Rumor',
	10 => 'Scary',
	11 => 'Stupid',
);

$dir = dirname( __FILE__ );

// Internationalization files
$wgMessagesDirs['LinkFilter'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['LinkFilter'] = "{$dir}/LinkFilter.i18n.php";
$wgExtensionMessagesFiles['LinkFilterAlias'] = "{$dir}/Link.alias.php";
// Namespace translations
$wgExtensionMessagesFiles['LinkNamespaces'] = "{$dir}/Link.namespaces.php";

// Some base classes to be autoloaded
$wgAutoloadClasses['Link'] = "{$dir}/LinkClass.php";
$wgAutoloadClasses['LinkList'] = "{$dir}/LinkClass.php";
$wgAutoloadClasses['LinkPage'] = "{$dir}/LinkPage.php";

// RSS feed class used on Special:LinksHome (replaces the hardcoded feed)
$wgAutoloadClasses['LinkFeed'] = "{$dir}/LinkFeed.php";

// Special pages
$wgAutoloadClasses['LinksHome'] = "{$dir}/SpecialLinksHome.php";
$wgSpecialPages['LinksHome'] = 'LinksHome';

$wgAutoloadClasses['LinkSubmit'] = "{$dir}/SpecialLinkSubmit.php";
$wgSpecialPages['LinkSubmit'] = 'LinkSubmit';

$wgAutoloadClasses['LinkRedirect'] = "{$dir}/SpecialLinkRedirect.php";
$wgSpecialPages['LinkRedirect'] = 'LinkRedirect';

$wgAutoloadClasses['LinkApprove'] = "{$dir}/SpecialLinkApprove.php";
$wgSpecialPages['LinkApprove'] = 'LinkApprove';

$wgAutoloadClasses['LinkEdit'] = "{$dir}/SpecialLinkEdit.php";
$wgSpecialPages['LinkEdit'] = 'LinkEdit';

// API module used by the JavaScript file
$wgAutoloadClasses['ApiLinkFilter'] = "{$dir}/ApiLinkFilter.php";
$wgAPIModules['linkfilter'] = 'ApiLinkFilter';

// Default setup for displaying sections
$wgLinkPageDisplay = array(
	'leftcolumn' => true,
	'rightcolumn' => false,
	'author' => true,
	'left_ad' => false,
	'popular_articles' => false,
	'in_the_news' => false,
	'comments_of_day' => true,
	'games' => true,
	'new_links' => false
);

// New user right, which is required to approve user-submitted links
$wgAvailableRights[] = 'linkadmin';
$wgGroupPermissions['linkadmin']['linkadmin'] = true;
$wgGroupPermissions['staff']['linkadmin'] = true;
$wgGroupPermissions['sysop']['linkadmin'] = true;

// Hooked functions
$wgAutoloadClasses['LinkFilterHooks'] = "{$dir}/LinkFilterHooks.php";

// Unset this variable when we don't need it, it's good practise (bug #47514)
unset( $dir );

// Hooked function registrations
$wgHooks['TitleMoveComplete'][] = 'LinkFilterHooks::updateLinkFilter';
$wgHooks['ArticleDelete'][] = 'LinkFilterHooks::deleteLinkFilter';
$wgHooks['ArticleFromTitle'][] = 'LinkFilterHooks::linkFromTitle';
$wgHooks['ParserFirstCallInit'][] = 'LinkFilterHooks::registerLinkFilterHook';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'LinkFilterHooks::applySchemaChanges';
$wgHooks['CanonicalNamespaces'][] = 'LinkFilterHooks::onCanonicalNamespaces';
// For the Renameuser extension
$wgHooks['RenameUserSQL'][] = 'LinkFilterHooks::onUserRename';
// Interaction with the Comments extension
$wgHooks['Comment::add'][] = 'LinkFilterHooks::onCommentAdd';
$wgHooks['Comment::delete'][] = 'LinkFilterHooks::onCommentDelete';
