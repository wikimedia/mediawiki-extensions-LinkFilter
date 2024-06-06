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

		if ( !$title->exists() ) {
			$this->dieWithError( 'apierror-missingtitle', 'missingtitle' );
		}

		// Rate limiting
		if ( $user->pingLimiter( 'edit' ) ) {
			$this->dieWithError( 'actionthrottledtext', 'throttled' );
		}

		// Now let's check whether we're even allowed to do this (delicious copypasta from core ApiEditPage.php)
		$this->checkTitleUserPermissions(
			$title,
			'edit',
			[ 'autoblock' => true ]
		);

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

		$link = new Link();
		$pageId = $title->getArticleID();
		$linkProperties = $link->getLinkByPageID( $pageId );
		$originalURL = $linkProperties['url'];

		// If we don't have a real URL, abort the mission.
		if ( !$link->isURL( $linkURL ) ) {
			$this->dieWithError( 'invalid-url', 'invalidurl' );
		}

		// Update link
		$link->editLink( $pageId, $toUpdate );

		// If the URL was changed, make an edit to the Link: page
		// as the Link: page contains (only) that data; the other properties
		// pertaining to a link are stored in the link table instead
		//
		// @todo FIXME: also this is basically duplicated here and in SpecialLinkEdit.php in nearly
		// identical form, except this one calls $newURL $linkURL instead and that's about it
		if ( isset( $toUpdate['link_url'] ) && $originalURL !== $linkURL ) {
			// @todo FIXME: a bit too heavy, getLinkWikiPage() calls getLink() again, which is
			// unnecessary for our needs, we already have all the data we need...
			$page = $link->getLinkWikiPage( $linkProperties['id'] );
			$pageContent = ContentHandler::makeContent(
				$linkURL,
				// Or we could use the existing $title variable but whatever...
				$page->getTitle()
			);

			$summary = $this->msg(
				'linkfilter-edit-summary-link-edited',
				$originalURL,
				$linkURL
			)->inContentLanguage()->text();

			if ( method_exists( $page, 'doUserEditContent' ) ) {
				// MW 1.36+
				$page->doUserEditContent(
					$pageContent,
					$user,
					$summary
				);
			} else {
				// @phan-suppress-next-line PhanUndeclaredMethod
				$page->doEditContent( $pageContent, $summary );
			}
		}

		$link->logAction(
			'edit',
			$user,
			$title,
			[
				// We don't have the *link* ID here (not that it's super useful anyway),
				// we get a Title object via the page title string, and the SpecialLinkEdit.php
				// page gets one via a *page* ID
				// '4::id' => $id,
				'5::url' => $linkURL ?? $originalURL,
				'6::desc' => $description ?? $linkProperties['description'],
				'7::type' => $linkType ?? $linkProperties['type']
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
