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
			$title = Title::newFromTextThrow( $linkTitle, NS_LINK );
		} catch ( Exception $e ) {
			// Reusing the same error message from MW core as SpecialLinkSubmit.php does
			$this->dieWithError( [ 'img-auth-badtitle', $linkTitle ], 'badtitle' );
		}

		// Rate limiting
		if ( $user->pingLimiter( 'edit' ) ) {
			$this->dieWithError( 'actionthrottledtext', 'throttled' );
		}

		// Basic anti-spam check
		// @todo FIXME: other than the 1st and 3rd var names in $checkForSpam and the way of
		// erroring out if $spammyFields is non-empty, this is verbatim pasta from SpecialLinkSubmit.php
		$hasSpam = false;
		$spammyFields = [];
		$checkForSpam = [ $linkTitle, $description, $linkURL ];

		foreach ( $checkForSpam as $fieldValue ) {
			$hasSpam = Link::validateSpamRegex( $fieldValue );
			if ( $hasSpam ) {
				$spammyFields[] = $fieldValue;
			}
		}

		if ( $spammyFields !== [] ) {
			$this->dieWithError( 'spamprotectiontext', 'spam' );
		}

		// CAPTCHA support if the ConfirmEdit extension is available
		if ( ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ) ) {
			$captcha = MediaWiki\Extension\ConfirmEdit\Hooks::getInstance();
			$request = $this->getRequest();
			if (
				(
					$captcha->triggersCaptcha( 'edit' ) ||
					$captcha->triggersCaptcha( 'create' ) ||
					$captcha->triggersCaptcha( 'addurl' )
				) &&
				!$captcha->canSkipCaptcha( $user, MediaWiki\MediaWikiServices::getInstance()->getMainConfig() ) &&
				!$captcha->passCaptchaFromRequest( $request, $user )
			) {
				// Get info about the CAPTCHA and return it to the user so that they can try solving it
				$theActualCaptcha = $captcha->getCaptcha();
				$index = $captcha->storeCaptcha( $theActualCaptcha );
				$rv = [
					'wpCaptchaWord' => $theActualCaptcha['question'],
					'wpCaptchaId' => $index
				];
				return $this->getResult()->addValue( null, $this->getModuleName(), $rv );
			}
		}

		// add to DB, or at least try
		$link = new Link();

		// If we have a real URL, only then add the link to the database.
		if ( !$link->isURL( $linkURL ) ) {
			$this->dieWithError( 'invalid-url', 'invalidurl' );
		} else {
			$id = $link->addLink(
				$linkTitle,
				$description,
				$linkURL,
				$linkType,
				$user
			);

			$link->logAction(
				'submit',
				$user,
				$title,
				[
					// '4::id' => $id,
					'5::url' => $linkURL,
					'6::desc' => $description,
					'7::type' => $linkType
				]
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
