<?php
/**
 * A special page for submitting new links for link admin approval.
 *
 * @file
 * @ingroup Extensions
 */
class SpecialLinkSubmit extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'LinkSubmit' );
	}

	/**
	 * Show this special page on Special:SpecialPages only for registered users
	 *
	 * @return bool
	 */
	public function isListed() {
		return $this->getUser()->isRegistered();
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par Parameter passed to the page, if any [unused]
	 * @return bool|void
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Anonymous users need to log in first
		if ( $user->isAnon() ) {
			throw new ErrorPageError( 'linkfilter-login-title', 'linkfilter-login-text' );
		}

		// Is the database locked or not?
		$this->checkReadOnly();

		// Blocked through Special:Block? No access for you either
		if ( $user->getBlock() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable False positive caused by core MW or something
			throw new UserBlockedError( $user->getBlock() );
		}

		// Add CSS & JS (JS is required by displayAddForm())
		$out->addModuleStyles( 'ext.linkFilter.styles' );
		$out->addModules( 'ext.linkFilter.scripts' );

		// If the request was POSTed and we haven't already submitted it, start
		// processing it
		if (
			$request->wasPosted() &&
			$_SESSION['alreadysubmitted'] == false &&
			$user->matchEditToken( $request->getVal( 'wpEditToken' ) )
		) {
			$_SESSION['alreadysubmitted'] = true;

			// No link title? Show an error message in that case.
			if ( !$request->getVal( 'lf_title' ) ) {
				$out->setPageTitle( $this->msg( 'error' )->text() );
				$out->addHTML( $this->displayAddForm() );
				return true;
			} else {
				// Perform some title validation to avoid a situation where we have a link
				// that has a title which cannot be converted to a valid Title, which thus
				// would prevent approving such a link, leaving link admins with no other
				// choice than to reject it
				try {
					$title = Title::newFromTextThrow( $request->getVal( 'lf_title' ) );
				} catch ( Exception $e ) {
					$out->setPageTitle( $this->msg( 'error' )->text() );
					$out->addHTML( $this->displayAddForm(
						// Yes, I'm reusing a core MW msg here. Naughty!
						$this->msg( 'img-auth-badtitle', $request->getVal( 'lf_title' ) )->escaped()
					) );
					return true;
				}
			}

			// The link must have a description, too!
			if ( !$request->getVal( 'lf_desc' ) ) {
				$out->setPageTitle( $this->msg( 'error' )->text() );
				$out->addHTML( $this->displayAddForm() );
				return true;
			}

			// ...and it needs a type
			if ( !$request->getInt( 'lf_type' ) ) {
				$out->setPageTitle( $this->msg( 'error' )->text() );
				$out->addHTML( $this->displayAddForm() );
				return true;
			}

			// Initialize a new instance of the Link class so that we can use
			// its non-static functions
			$link = new Link();

			// If we have a real URL, only then add the link to the database.
			if ( $link->isURL( $request->getVal( 'lf_URL' ) ) ) {
				$link->addLink(
					$request->getVal( 'lf_title' ),
					$request->getVal( 'lf_desc' ),
					htmlspecialchars( $request->getVal( 'lf_URL' ), ENT_QUOTES ),
					$request->getInt( 'lf_type' ),
					$user
				);
				// Great success, comrade!
				$out->setPageTitle( $this->msg( 'linkfilter-submit-success-title' )->escaped() );
				$out->addHTML(
					'<div class="link-success-text">' .
						$this->msg( 'linkfilter-submit-success-text' )->escaped() .
					'</div>
					<div class="link-submit-button">
						<form method="get" action="' . Link::getSubmitLinkURL() . '">
							<input type="submit" class="site-button" onclick="window.location=\'' .
								Link::getSubmitLinkURL() . '\'" value="' .
								$this->msg( 'linkfilter-submit-another' )->escaped() . '" />
						</form>
					</div>'
				);
			}
		} else {
			// Something went wrong...
			$out->setPageTitle( $this->msg( 'linkfilter-submit-title' )->escaped() );
			$out->addHTML( $this->displayAddForm() );
		}
	}

	/**
	 * Display the form for submitting a new link.
	 *
	 * @param string $errorMsg Error message to be displayed, if any
	 * @return string HTML
	 */
	function displayAddForm( $errorMsg = '' ) {
		$request = $this->getRequest();

		// Preserve the URL in case if the form was submitted but there were errors
		$url = $request->getVal( '_url', $request->getVal( 'lf_URL' ) );
		$title = $request->getVal( '_title' );

		if ( !$url ) {
			$url = 'http://';
		}

		if ( !$title ) {
			$titleFromRequest = $request->getVal( 'lf_title' );
			if ( isset( $titleFromRequest ) ) {
				$title = $titleFromRequest;
			}
		}

		$_SESSION['alreadysubmitted'] = false;

		$descFromRequest = $request->getVal( 'lf_desc' );
		$lf_desc = $descFromRequest ?: '';

		$output = '';
		if ( $errorMsg !== '' ) {
			$output .= Html::errorBox( $errorMsg );
		}
		$output .= '<div class="lr-left">

			<div class="link-home-navigation">
				<a href="' . Link::getHomeLinkURL() . '">' .
					$this->msg( 'linkfilter-home-button' )->escaped() . '</a>';

		// Show a link to the LinkAdmin page for privileged users
		if ( Link::canAdmin( $this->getUser() ) ) {
			$output .= ' <a href="' . Link::getLinkAdminURL() . '">' .
				$this->msg( 'linkfilter-approve-links' )->escaped() . '</a>';
		}

		$output .= '<div class="visualClear"></div>
			</div>
			<form name="link" id="linksubmit" method="post" action="">
				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-title' )->escaped() . '</label>
				</div>
				<input tabindex="1" class="lr-input" type="text" name="lf_title" id="lf_title" value="' .
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal Whatever...
					htmlspecialchars( $title, ENT_QUOTES ) . '" maxlength="150" />
				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-url' )->escaped() . '</label>
				</div>
				<input tabindex="2" class="lr-input" type="text" name="lf_URL" id="lf_URL" value="' . htmlspecialchars( $url, ENT_QUOTES ) . '"/>

				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-description' )->escaped() . '</label>
				</div>
				<div class="link-characters-left">' .
					$this->msg( 'linkfilter-description-max' )->escaped() . ' - ' .
					$this->msg( 'linkfilter-description-left', '<span id="desc-remaining">300</span>' )->parse() .
				'</div>
				<textarea tabindex="3" class="lr-input" rows="4" name="lf_desc" id="lf_desc">' .
					htmlspecialchars( $lf_desc, ENT_QUOTES ) .
				'</textarea>

				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-type' )->escaped() . '</label>
				</div>
				<select tabindex="4" name="lf_type" id="lf_type">
				<option value="">-</option>';
		$linkTypes = Link::getLinkTypes();
		foreach ( $linkTypes as $id => $type ) {
			// Preserve value in case if the form was submitted but there were errors
			$output .= Xml::option( $type, $id, ( $id === $request->getInt( 'lf_type' ) ) );
		}
		$output .= '</select>
				<div class="link-submit-button">
					<input tabindex="5" class="site-button" type="submit" id="link-submit-button" value="' . $this->msg( 'linkfilter-submit-button' )->escaped() . '" />
				</div>' .
				Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() ) .
			'</form>
		</div>';

		$output .= '<div class="lr-right">' .
			$this->msg( 'linkfilter-instructions' )->inContentLanguage()->parse() .
		'</div>
		<div class="visualClear"></div>';

		return $output;
	}

}
