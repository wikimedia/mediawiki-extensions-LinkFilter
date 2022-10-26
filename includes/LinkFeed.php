<?php
/**
 * Generates a RSS feed of the most recent submitted links.
 *
 * @file
 * @ingroup Extensions
 */
class LinkFeed extends RSSFeed {

	/**
	 * Format a date given a timestamp. If a timestamp is not given, nothing is returned
	 *
	 * @note Copied from core /includes/changes/RSSFeed.php as of MW 1.35 and renamed
	 * from formatTime() to formatTimeCustom() since the original method is private :-(
	 *
	 * @param int|null $ts Timestamp
	 * @return string|null Date string
	 */
	private function formatTimeCustom( $ts ) {
		if ( $ts ) {
			return gmdate( 'D, d M Y H:i:s \G\M\T', (int)wfTimestamp( TS_UNIX, $ts ) );
		}
		return null;
	}

	/**
	 * Output the header for this feed.
	 */
	function outHeader() {
		global $wgServer, $wgScriptPath, $wgEmergencyContact;

		$stuff = '';
		// This message is used by the ArticleMetaDescription extension
		$message = wfMessage( 'description' )->inContentLanguage();
		if ( !$message->isDisabled() ) {
			$stuff = '<description>' . $message->escaped() . "</description>\n\t\t";
		}

		$this->outXmlHeader();
?><rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:feedburner="http://rssnamespace.org/feedburner/ext/1.0">
	<channel>
		<title><?php echo wfMessage( 'linkfilter-feed-title' )->parse() ?></title>
		<link><?php echo $wgServer . $wgScriptPath ?></link>
		<?php echo $stuff ?><language><?php echo $this->getLanguage() ?></language>
		<pubDate><?php echo $this->formatTimeCustom( time() ) ?></pubDate>
		<managingEditor><?php echo $wgEmergencyContact ?></managingEditor>
		<webMaster><?php echo $wgEmergencyContact ?></webMaster>
<?php
	}

	/**
	 * Output an individual feed item.
	 *
	 * @param FeedItem $item Item to be output
	 */
	function outItem( $item ) {
		$url = $item->getUrl();
?>
	<item>
		<title><?php echo $item->getTitle() ?></title>
		<description><![CDATA[<?php echo $item->getDescription() ?>]]></description>
		<link><?php echo wfExpandUrl( $url, PROTO_CURRENT ) ?></link>
		<guid isPermaLink="true"><?php echo wfExpandUrl( $url, PROTO_CURRENT ) ?></guid>
	</item>
<?php
	}

}
