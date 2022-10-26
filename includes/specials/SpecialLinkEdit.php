<?php

class SpecialLinkEdit extends UnlistedSpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'LinkEdit' );
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par Parameter passed to the page, if any [unused]
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		// Check permissions
		if ( !Link::canAdmin( $user ) ) {
			$this->requireLogin();
		}

		// Is the database locked or not?
		$this->checkReadOnly();

		// No access for blocked users
		if ( $user->getBlock() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable False positive caused by core MW or something
			throw new UserBlockedError( $user->getBlock() );
		}

		// Add CSS & JS
		$out->addModuleStyles( 'ext.linkFilter.styles' );
		$out->addModules( 'ext.linkFilter.scripts' );

		if (
			$request->wasPosted() &&
			$_SESSION['alreadysubmitted'] == false &&
			$user->matchEditToken( $request->getVal( 'wpEditToken' ) )
		) {
			$_SESSION['alreadysubmitted'] = true;

			// Update link
			$dbw = wfGetDB( DB_PRIMARY );
			$dbw->update(
				'link',
				[
					'link_url' => $_POST['lf_URL'],
					'link_description' => $_POST['lf_desc'],
					'link_type' => intval( $_POST['lf_type'] )
				],
				[
					'link_page_id' => $request->getInt( 'id' )
				],
				__METHOD__
			);

			$title = Title::newFromId( $request->getInt( 'id' ) );
			$out->redirect( $title->getFullURL() );
		} else {
			$out->addHTML( $this->displayEditForm() );
		}
	}

	/**
	 * Display the form for editing a link entry.
	 *
	 * @return string HTML
	 */
	function displayEditForm() {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$url = $request->getVal( '_url' );
		$title = $request->getVal( '_title' );

		$l = new Link();
		$link = $l->getLinkByPageID( $request->getInt( 'id' ) );
		$description = '';

		if ( is_array( $link ) && !empty( $link ) ) {
			$url = htmlspecialchars( $link['url'], ENT_QUOTES );
			$description = htmlspecialchars( $link['description'], ENT_QUOTES );
		} else {
			$title = SpecialPage::getTitleFor( 'LinkSubmit' );
			$out->redirect( $title->getFullURL() );
			return '';
		}

		$out->setPageTitle( $this->msg( 'linkfilter-edit-title', $link['title'] )->text() );

		$_SESSION['alreadysubmitted'] = false;

		$output = '<div class="lr-left">

			<div class="link-home-navigation">
				<a href="' . Link::getHomeLinkURL() . '">' .
					$this->msg( 'linkfilter-home-button' )->escaped() . '</a>';

		if ( Link::canAdmin( $this->getUser() ) ) {
			$output .= ' <a href="' . Link::getLinkAdminURL() . '">' .
				$this->msg( 'linkfilter-approve-links' )->escaped() . '</a>';
		}

		$output .= '<div class="visualClear"></div>
			</div>
			<form name="link" id="linksubmit" method="post" action="">
				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-url' )->escaped() . '</label>
				</div>
				<input tabindex="2" class="lr-input" type="text" name="lf_URL" id="lf_URL" value="' . $url . '"/>

				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-description' )->escaped() . '</label>
				</div>
				<div class="link-characters-left">' .
					$this->msg( 'linkfilter-description-max' )->escaped() . ' - ' .
					$this->msg( 'linkfilter-description-left', '<span id="desc-remaining">300</span>' )->parse() .
				'</div>
				<textarea tabindex="3" class="lr-input" rows="4" name="lf_desc" id="lf_desc">'
				. $description .
				'</textarea>

				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-type' )->escaped() . '</label>
				</div>
				<select tabindex="4" name="lf_type" id="lf_type">
				<option value="">-</option>';
		$linkTypes = Link::getLinkTypes();
		foreach ( $linkTypes as $id => $type ) {
			$output .= Xml::option( $type, $id, ( $link['type'] == $id ) );
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
