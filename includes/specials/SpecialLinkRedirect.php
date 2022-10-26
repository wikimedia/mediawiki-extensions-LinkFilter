<?php

class SpecialLinkRedirect extends UnlistedSpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'LinkRedirect' );
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par Parameter passed to the page, if any [unused]
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$out->setArticleBodyOnly( true );
		$sk = $this->getOutput()->getSkin();
		$url = htmlspecialchars( $this->getRequest()->getVal( 'url' ), ENT_QUOTES );
		$out->addHTML(
			"<html>
				<body onload=window.location=\"{$url}\">
				{$sk->bottomScripts()}
				</body>
			</html>"
		);
	}
}
