<?php

class LinkList {

	/**
	 * @param int $status Link status
	 * @param int $type Link type (one of Link::$link_types integers)
	 * @param int $limit LIMIT for SQL query, 0 by default.
	 * @param int $page Used to build the OFFSET in the SQL query.
	 * @param string $order ORDER BY clause for SQL query.
	 * @return array
	 */
	public function getLinkList( $status, $type, $limit = 0, $page = 0, $order = 'link_submit_date' ) {
		$dbr = wfGetDB( DB_REPLICA );

		$params = [];
		$params['ORDER BY'] = "$order DESC";
		if ( $limit ) {
			$params['LIMIT'] = $limit;
		}
		if ( $page ) {
			$params['OFFSET'] = $page * $limit - ( $limit );
		}

		$where = [];
		if ( $type > 0 ) {
			$where['link_type'] = $type;
		}
		if ( is_numeric( $status ) ) {
			$where['link_status'] = $status;
		}

		$dbr = wfGetDB( DB_PRIMARY );

		$res = $dbr->select(
			[ 'link' ],
			[
				'link_id', 'link_page_id', 'link_type', 'link_status',
				'link_name', 'link_description', 'link_url',
				'link_submit_date', 'link_approved_date', 'link_comment_count',
				'link_submitter_actor'
			],
			$where,
			__METHOD__,
			$params
		);

		$links = [];

		foreach ( $res as $row ) {
			$linkPage = Title::makeTitleSafe( NS_LINK, $row->link_name );
			$links[] = [
				'id' => $row->link_id,
				'timestamp' => wfTimestamp( TS_UNIX, $row->link_submit_date ),
				'approved_timestamp' => wfTimestamp( TS_UNIX, $row->link_approved_date ),
				'url' => $row->link_url,
				'title' => $row->link_name,
				'description' => $row->link_description,
				'page_id' => $row->link_page_id,
				'type' => $row->link_type,
				'status' => $row->link_status,
				'type_name' => Link::getLinkType( $row->link_type ),
				'wiki_page' => ( ( $linkPage ) ? htmlspecialchars( $linkPage->getFullURL(), ENT_QUOTES ) : null ),
				'comments' => ( $row->link_comment_count ?: 0 ),
				'actor' => $row->link_submitter_actor
			];
		}

		return $links;
	}

	/**
	 * Get the number of links matching the given criteria.
	 *
	 * @param int $status Link status
	 * @param int $type Link type (one of Link::$link_types integers)
	 * @return int Number of links matching the given criteria
	 */
	public function getLinkListCount( $status, $type ) {
		$dbr = wfGetDB( DB_REPLICA );

		$where = [];
		if ( $type > 0 ) {
			$where['link_type'] = $type;
		}
		if ( is_numeric( $status ) ) {
			$where['link_status'] = $status;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$s = $dbr->selectRow(
			'link',
			[ 'COUNT(*) AS count' ],
			$where,
			__METHOD__
		);

		return $s->count;
	}

}
