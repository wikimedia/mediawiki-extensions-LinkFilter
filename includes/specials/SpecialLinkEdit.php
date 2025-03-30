<?php

use MediaWiki\MediaWikiServices;

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

			$id = $request->getInt( 'id' );
			$newURL = $request->getText( 'lf_URL' );

			// The page has to exist first before it can be edited
			// LinkFilter isn't MW core, you gotta submit a link first and then
			// have it approved before it's possible to edit it
			$title = Title::newFromId( $id );
			if ( !$title->exists() ) {
				$out->addHTML( Html::errorBox( $this->msg(
					'htmlform-title-not-exists',
					$title->getPrefixedText()
				)->parse() ) );
				return;
			}

			// Check that the user is allowed to edit this page; they may not necessarily be
			// e.g. if the page is protected and they're not allowed to edit protected pages
			// (weird, yet possible, edge case if linkadmin permissions are assigned to a group
			// that *cannot* change protection settings)
			$services = MediaWikiServices::getInstance();
			if ( !$services->getPermissionManager()->userCan( 'edit', $user, $title ) ) {
				$out->addHTML( Html::errorBox( $this->msg(
					'protectedpagetext'
				)->parse() ) );
				return;
			}

			$link = new Link();
			$linkProperties = $link->getLinkByPageID( $id );
			$originalURL = $linkProperties['url'];

			// Update link
			$link->editLink(
				$id,
				[
					'link_url' => $newURL,
					'link_description' => $_POST['lf_desc'],
					'link_type' => intval( $_POST['lf_type'] )
				]
			);

			// If the URL was changed, make an edit to the Link: page
			// as the Link: page contains (only) that data; the other properties
			// pertaining to a link are stored in the link table instead
			if ( $originalURL !== $newURL ) {
				// @todo FIXME: a bit too heavy, getLinkWikiPage() calls getLink() again, which is
				// unnecessary for our needs, we already have all the data we need...
				$page = $link->getLinkWikiPage( $linkProperties['id'] );
				$pageContent = ContentHandler::makeContent(
					$request->getText( 'lf_URL' ),
					$page->getTitle()
				);

				$summary = $this->msg(
					'linkfilter-edit-summary-link-edited',
					$originalURL,
					$newURL
				)->inContentLanguage()->text();

				$page->doUserEditContent(
					$pageContent,
					$user,
					$summary
				);
			}

			$link->logAction(
				'edit',
				$user,
				$title,
				[
					// $id is NOT a link ID but rather a *page* ID, d'oh...
					// '4::id' => $id,
					'5::url' => $request->getText( 'lf_URL' ),
					'6::desc' => $request->getText( 'lf_desc' ),
					'7::type' => $request->getText( 'lf_type' )
				]
			);

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

		if ( is_array( $link ) && $link ) {
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
