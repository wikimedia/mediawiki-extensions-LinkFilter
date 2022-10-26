<?php

use Wikimedia\AtEase\AtEase;

/**
 * LinkFilter API module
 *
 * @file
 * @ingroup API
 * @see https://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
 */
class ApiLinkFilter extends ApiBase {

	public function execute() {
		// Get the request parameters
		$params = $this->extractRequestParams();

		AtEase::suppressWarnings();
		$id = $params['id'];
		$status = $params['status'];
		AtEase::restoreWarnings();

		// Make sure that we have the parameters we need and that their datatypes
		// are even somewhat sane
		if (
			// @phan-suppress-next-line PhanImpossibleTypeComparison
			!$id || $id === null || !is_numeric( $id ) ||
			// @phan-suppress-next-line PhanImpossibleTypeComparison
			!$status || $status === null || !is_numeric( $status )
		) {
			$this->dieWithError( [ 'apierror-missingparam' ], 'missingparam' );
		}

		// Check permissions
		if ( !Link::canAdmin( $this->getUser() ) ) {
			return '';
		}

		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->update(
			'link',
			[ 'link_status' => intval( $status ) ],
			[ 'link_id' => intval( $id ) ],
			__METHOD__
		);

		if ( $status == 1 ) {
			$l = new Link();
			$l->approveLink( $id );
		}

		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(),
			[ 'result' => 'ok' ]
		);

		return true;
	}

	/**
	 * Does this module require a POST request instead of a standard GET?
	 *
	 * @return bool
	 */
	function mustBePosted() {
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		return true;
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'id' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			],
			'status' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	public function getExamplesMessages() {
		return [
			'action=linkfilter&id=37&status=2'
				=> 'apihelp-linkfilter-example-1'
		];
	}
}
