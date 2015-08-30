<?php
/**
 * A special page for submitting new links for link admin approval.
 *
 * @file
 * @ingroup Extensions
 */
class LinkSubmit extends SpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'LinkSubmit' );
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Anonymous users need to log in first
		if ( $user->isAnon() ) {
			throw new ErrorPageError( 'linkfilter-login-title', 'linkfilter-login-text' );
			return true;
		}

		// Is the database locked or not?
		if ( wfReadOnly() ) {
			$out->readOnlyPage();
			return false;
		}

		// Blocked through Special:Block? No access for you either
		if ( $user->isBlocked() ) {
			$out->blockedPage( false );
			return false;
		}

		// Add CSS & JS (JS is required by displayAddForm())
		$out->addModules( 'ext.linkFilter' );

		// If the request was POSTed and we haven't already submitted it, start
		// processing it
		if ( $request->wasPosted() && $_SESSION['alreadysubmitted'] == false ) {
			$_SESSION['alreadysubmitted'] = true;

			// No link title? Show an error message in that case.
			if ( !$request->getVal( 'lf_title' ) ) {
				$out->setPageTitle( $this->msg( 'error' )->text() );
				$out->addHTML( $this->displayAddForm() );
				return true;
			}

			// The link must have a description, too!
			if ( !$request->getVal( 'lf_desc' ) ) {
				$out->setPageTitle( $this->msg( 'error' )->text() );
				$out->addHTML( $this->displayAddForm() );
				return true;
			}

			// ...and it needs a type
			if ( !$request->getInt( 'lf_type' ) ) {
				$out->setPageTitle( $this->msg( 'error' )-text() );
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
					htmlspecialchars( $request->getVal( 'lf_URL' ) ),
					$request->getInt( 'lf_type' )
				);
				// Great success, comrade!
				$out->setPageTitle( $this->msg( 'linkfilter-submit-success-title' )->plain() );
				$out->addHTML(
					'<div class="link-success-text">' .
						$this->msg( 'linkfilter-submit-success-text' )->text() .
					'</div>
					<div class="link-submit-button">
						<input type="button" onclick="window.location=\'' .
							Link::getSubmitLinkURL() . '\'" value="' .
							$this->msg( 'linkfilter-submit-another' )->plain() . '" />
					</div>'
				);
			}
		} else { // Something went wrong...
			$out->setPageTitle( $this->msg( 'linkfilter-submit-title' )->plain() );
			$out->addHTML( $this->displayAddForm() );
		}
	}

	/**
	 * Display the form for submitting a new link.
	 * @return String: HTML
	 */
	function displayAddForm() {
		$request = $this->getRequest();

		$url = $request->getVal( '_url' );
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
		$lf_desc = isset( $descFromRequest ) ? $descFromRequest : '';

		$output = '<div class="lr-left">

			<div class="link-home-navigation">
				<a href="' . Link::getHomeLinkURL() . '">' .
					$this->msg( 'linkfilter-home-button' )->plain() . '</a>';

		// Show a link to the LinkAdmin page for privileged users
		if ( Link::canAdmin() ) {
			$output .= ' <a href="' . Link::getLinkAdminURL() . '">' .
				$this->msg( 'linkfilter-approve-links' )->text() . '</a>';
		}

		$output .= '<div class="visualClear"></div>
			</div>
			<form name="link" id="linksubmit" method="post" action="">
				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-title' )->plain() . '</label>
				</div>
				<input tabindex="1" class="lr-input" type="text" name="lf_title" id="lf_title" value="' . $title . '" maxlength="150" />
				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-url' )->plain() . '</label>
				</div>
				<input tabindex="2" class="lr-input" type="text" name="lf_URL" id="lf_URL" value="' . $url . '"/>

				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-description' )->plain() . '</label>
				</div>
				<div class="link-characters-left">' .
					$this->msg( 'linkfilter-description-max' )->text() . ' - ' .
					$this->msg( 'linkfilter-description-left', '<span id="desc-remaining">300</span>' )->text() .
				'</div>
				<textarea tabindex="3" class="lr-input" rows="4" name="lf_desc" id="lf_desc" value="' . $lf_desc . '"></textarea>

				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-type' )->plain() . '</label>
				</div>
				<select tabindex="4" name="lf_type" id="lf_type">
				<option value="">-</option>';
		$linkTypes = Link::getLinkTypes();
		foreach ( $linkTypes as $id => $type ) {
			$output .= "<option value=\"{$id}\">{$type}</option>";
		}
		$output .= '</select>
				<div class="link-submit-button">
					<input tabindex="5" class="site-button" type="button" id="link-submit-button" value="' . $this->msg( 'linkfilter-submit-button' )->text() . '" />
				</div>
			</form>
		</div>';

		$output .= '<div class="lr-right">' .
			$this->msg( 'linkfilter-instructions' )->inContentLanguage()->parse() .
		'</div>
		<div class="visualClear"></div>';

		return $output;
	}

}
