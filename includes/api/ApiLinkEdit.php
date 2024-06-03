<?php
/**
 * Link editing API module, i.e. the api.php version of Special:LinkEdit.
 *
 * @file
 * @date 23 May 2024
 */

use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

/**
 * @ingroup API
 */
class ApiLinkEdit extends ApiBase {

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 */
	public function __construct( ApiMain $mainModule, $moduleName ) {
		parent::__construct( $mainModule, $moduleName );
	}

	/** @inheritDoc */
	public function execute() {
		$user = $this->getUser();
		$params = $this->extractRequestParams();

		if ( !$user->isRegistered() ) {
			$this->dieWithError( 'linkfilter-login-text', 'notloggedin' );
		}

		if ( !Link::canAdmin( $user ) ) {
			$this->dieWithError( [ 'apierror-permissiondenied', $this->msg( 'action-linkadmin' ) ] );
		}

		// Check blocks
		if ( $user->getBlock() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Block is checked and not null
			$this->dieBlocked( $user->getBlock() );
		}

		// Ensure we have a reason to be editing this link
		$this->requireAtLeastOneParameter( $params, 'url', 'description', 'type' );

		$toUpdate = [];
		$linkTitle = $params['title'];

		// Ensure that the user-supplied garbage can be converted into a valid Title object
		// and if not, bail out immediately.
		try {
			$title = Title::newFromTextThrow( $linkTitle, NS_LINK );
		} catch ( Exception $e ) {
			// Reusing the same error message from MW core as SpecialLinkSubmit.php does
			$this->dieWithError( [ 'img-auth-badtitle', $linkTitle ], 'badtitle' );
		}

		$linkURL = $params['url'];
		if ( $linkURL ) {
			$toUpdate['link_url'] = $linkURL;
		}
		$description = $params['description'];
		if ( $description ) {
			$toUpdate['link_description'] = $description;
		}
		$linkType = $params['type'];
		if ( $linkType ) {
			$toUpdate['link_type'] = (int)$linkType;
		}

		$link = new Link();

		// If we don't have a real URL, abort the mission.
		if ( !$link->isURL( $linkURL ) ) {
			$this->dieWithError( 'invalid-url', 'invalidurl' );
		}

		// Update link
		$link->editLink( $title->getArticleID(), $toUpdate );

		// @todo FIXME: if the URL was changed, it should generate an edit
		// to the Link: page in question (as the URL, and only that, is on the
		// Link: page; all the other properties like description or type are stored
		// in the link table and editable via this API module and/or its special page equivalent)

		$link->logAction(
			'edit',
			$user,
			$title,
			[
				// We don't have the *link* ID here (not that it's super useful anyway),
				// we get a Title object via the page title string, and the SpecialLinkEdit.php
				// page gets one via a *page* ID
				// '4::id' => $id,
				'5::url' => $linkURL,
				'6::desc' => $description,
				'7::type' => $linkType
			]
		);

		// Report success back to the user here
		$this->getResult()->addValue( null, $this->getModuleName(), [
			'status' => 'OK',
			'url' => $title->getFullURL()
		] );
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		global $wgLinkFilterTypes;
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				// Enforce a maximum length of 150 characters just as SpecialLinkSubmit.php does
				StringDef::PARAM_MAX_CHARS => 150,
			],
			'description' => [
				ParamValidator::PARAM_TYPE => 'string',
				// As per above/SpecialLinkSubmit.php
				StringDef::PARAM_MAX_CHARS => 300,
			],
			'type' => [
				ParamValidator::PARAM_TYPE => array_map( 'strval', array_keys( $wgLinkFilterTypes ) ),
			],
			'url' => [
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=linkedit&title=Test link&' .
				'description=Cute little animals to brighten up your day!&' .
				'type=4&url=https://cats.example.com/new-cat-photos-for-today/'
			=> 'apihelp-linkedit-example-1'
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:LinkFilter/API';
	}
}
