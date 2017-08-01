<?php

class LinkEdit extends UnlistedSpecialPage {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'LinkEdit' );
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

		// Check permissions
		if ( !Link::canAdmin() ) {
			$this->displayRestrictionError();
			return;
		}

		// Is the database locked or not?
		$this->checkReadOnly();

		// No access for blocked users
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $user->getBlock() );
		}

		// Add CSS & JS
		$out->addModuleStyles( 'ext.linkFilter.styles' );
		$out->addModules( 'ext.linkFilter.scripts' );

		if (
			$request->wasPosted() &&
			$_SESSION['alreadysubmitted'] == false &&
			$user->matchEditToken( $request->getVal( 'wpEditToken' ) )
		)
		{
			$_SESSION['alreadysubmitted'] = true;

			// Update link
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update(
				'link',
				array(
					'link_url' => $_POST['lf_URL'],
					'link_description' => $_POST['lf_desc'],
					'link_type' => intval( $_POST['lf_type'] )
				),
				/* WHERE */array(
					'link_page_id' => $request->getInt( 'id' )
				),
				__METHOD__
			);

			$title = Title::newFromId( $request->getInt( 'id' ) );
			$out->redirect( $title->getFullURL() );
		} else {
			$out->addHTML( $this->displayEditForm() );
		}
	}

	function displayEditForm() {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$url = $request->getVal( '_url' );
		$title = $request->getVal( '_title' );

		$l = new Link();
		$link = $l->getLinkByPageID( $request->getInt( 'id' ) );

		if ( is_array( $link ) && !empty( $link ) ) {
			$url = htmlspecialchars( $link['url'], ENT_QUOTES );
			$description = htmlspecialchars( $link['description'], ENT_QUOTES );
		} else {
			$title = SpecialPage::getTitleFor( 'LinkSubmit' );
			$out->redirect( $title->getFullURL() );
		}

		$out->setPageTitle( $this->msg( 'linkfilter-edit-title', $link['title'] )->text() );

		$_SESSION['alreadysubmitted'] = false;

		$output = '<div class="lr-left">

			<div class="link-home-navigation">
				<a href="' . Link::getHomeLinkURL() . '">' .
					$this->msg( 'linkfilter-home-button' )->text() . '</a>';

		if ( Link::canAdmin() ) {
			$output .= ' <a href="' . Link::getLinkAdminURL() . '">' .
				$this->msg( 'linkfilter-approve-links' )->text() . '</a>';
		}

		$output .= '<div class="visualClear"></div>
			</div>
			<form name="link" id="linksubmit" method="post" action="">
				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-url' )->text() . '</label>
				</div>
				<input tabindex="2" class="lr-input" type="text" name="lf_URL" id="lf_URL" value="' . $url . '"/>

				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-description' )->text() . '</label>
				</div>
				<div class="link-characters-left">' .
					$this->msg( 'linkfilter-description-max' )->text() . ' - ' .
					$this->msg( 'linkfilter-description-left', '<span id="desc-remaining">300</span>' )->text() .
				'</div>
				<textarea tabindex="3" class="lr-input" rows="4" name="lf_desc" id="lf_desc">'
				. $description .
				'</textarea>

				<div class="link-submit-title">
					<label>' . $this->msg( 'linkfilter-type' )->text() . '</label>
				</div>
				<select tabindex="4" name="lf_type" id="lf_type">
				<option value="">-</option>';
		$linkTypes = Link::getLinkTypes();
		foreach ( $linkTypes as $id => $type ) {
			$selected = '';
			if ( $link['type'] == $id ) {
				$selected = ' selected="selected"';
			}
			$output .= "<option value=\"{$id}\"{$selected}>{$type}</option>";
		}
		$output .= '</select>
				<div class="link-submit-button">
					<input tabindex="5" class="site-button" type="button" id="link-submit-button" value="' . $this->msg( 'linkfilter-submit-button' )->text() . '" />
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