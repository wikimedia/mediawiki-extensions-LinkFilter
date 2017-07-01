<?php
/**
 * LinkFilter API module
 *
 * @file
 * @ingroup API
 * @date 29 August 2013
 * @see http://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
 */
class ApiLinkFilter extends ApiBase {

	public function execute() {
		// Get the request parameters
		$params = $this->extractRequestParams();

		wfSuppressWarnings();
		$id = $params['id'];
		$status = $params['status'];
		wfRestoreWarnings();

		// Make sure that we have the parameters we need and that their datatypes
		// are even somewhat sane
		if (
			!$id || $id === null || !is_numeric( $id ) ||
			!$status || $status === null || !is_numeric( $status )
		)
		{
			$this->dieUsageMsg( 'missingparam' );
		}

		// Check permissions
		if ( !Link::canAdmin() ) {
			return '';
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'link',
			/* SET */array( 'link_status' => intval( $status ) ),
			/* WHERE */array( 'link_id' => intval( $id ) ),
			__METHOD__
		);

		if ( $status == 1 ) {
			$l = new Link();
			$l->approveLink( $id );
		}

		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(),
			array( 'result' => 'ok' )
		);

		return true;
	}

	/**
	 * Does this module require a POST request instead of a standard GET?
	 *
	 * @return Boolean
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
	 * @return Array
	 */
	public function getAllowedParams() {
		return array(
			'id' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			),
			'status' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			),
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	public function getExamplesMessages() {
		return array(
			'action=linkfilter&id=37&status=2'
				=> 'apihelp-linkfilter-example-1'
		);
	}
}
