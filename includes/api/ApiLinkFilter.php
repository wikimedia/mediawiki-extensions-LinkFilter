<?php

use MediaWiki\MediaWikiServices;

/**
 * LinkFilter API module for approving and rejecting user-submitted links.
 *
 * @file
 * @ingroup API
 * @see https://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
 */
class ApiLinkFilter extends ApiBase {

	public function execute() {
		// Get the request parameters
		$params = $this->extractRequestParams();

		$id = $params['id'];
		$status = $params['status'];

		// Check permissions
		$user = $this->getUser();
		if ( !Link::canAdmin( $user ) ) {
			return '';
		}

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->update(
			'link',
			[ 'link_status' => intval( $status ) ],
			[ 'link_id' => intval( $id ) ],
			__METHOD__
		);

		$wasApproved = ( $status == LinkStatus::APPROVED );

		$link = new Link();

		if ( $wasApproved ) {
			$link->approveLink( $id );

			$zelda = $link->getLink( $id );

			$link->logAction(
				'approve',
				$user,
				$link->getLinkWikiPage( $id ),
				[
					'4::id' => $id,
					'5::url' => $zelda['url'],
					'6::desc' => $zelda['description'],
					// store the numeric type ID, can be easily enough converted into
					// a more human-friendly string, e.g. with Link::getLinkType();
					// that's precisely why this *isn't* using $zelda['type_name'] here
					'7::type' => $zelda['type']
				]
			);
		} else {
			$zelda = $link->getLink( $id );

			$link->logAction(
				'reject',
				$user,
				$link->getLinkWikiPage( $id ),
				[
					'4::id' => $id,
					'5::url' => $zelda['url'],
					'6::desc' => $zelda['description'],
					// store the numeric type ID, can be easily enough converted into
					// a more human-friendly string, e.g. with Link::getLinkType();
					// that's precisely why this *isn't* using $zelda['type_name'] here
					'7::type' => $zelda['type']
				]
			);
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
