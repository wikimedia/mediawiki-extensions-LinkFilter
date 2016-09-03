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

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'LinkFilter' );
	$wgMessagesDirs['LinkFilter'] =  __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for LinkFilter extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the LinkFilter extension requires MediaWiki 1.25+' );
}