<?php
/**
 * Link submission API module, i.e. the api.php version of Special:LinkSubmit.
 *
 * @file
 * @date 23 May 2024
 */

use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

/**
 * @ingroup API
 */
class ApiLinkSubmit extends ApiBase {

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

		// Check blocks
		if ( $user->getBlock() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Block is checked and not null
			$this->dieBlocked( $user->getBlock() );
		}

		$linkTitle = $params['title'];
		$linkURL = $params['url'];
		$description = $params['description'];
		$linkType = $params['type'];

		// Ensure that the user-supplied garbage can be converted into a valid Title object
		// and if not, bail out immediately.
		try {
			$title = Title::newFromTextThrow( $linkTitle );
		} catch ( Exception $e ) {
			// Reusing the same error message from MW core as SpecialLinkSubmit.php does
			$this->dieWithError( [ 'img-auth-badtitle', $linkTitle ], 'badtitle' );
		}

		// add to DB, or at least try
		$link = new Link();

		// If we have a real URL, only then add the link to the database.
		if ( !$link->isURL( $linkURL ) ) {
			$this->dieWithError( 'invalid-url', 'invalidurl' );
		} else {
			$link->addLink(
				$linkTitle,
				$description,
				$linkURL,
				$linkType,
				$user
			);

			// Report success back to the user here
			$this->getResult()->addValue( null, $this->getModuleName(), [ 'status' => 'OK' ] );
		}
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
				ParamValidator::PARAM_REQUIRED => true,
				// As per above/SpecialLinkSubmit.php
				StringDef::PARAM_MAX_CHARS => 300,
			],
			'type' => [
				ParamValidator::PARAM_TYPE => array_map( 'strval', array_keys( $wgLinkFilterTypes ) ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			'url' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
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
			'action=linksubmit&title=Funny cats and kittens&' .
				'description=Cute little animals to brighten up your day!&' .
				'type=4&url=https://cats.example.com/new-cat-photos-for-today/'
			=> 'apihelp-linksubmit-example-1'
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:LinkFilter/API';
	}
}
