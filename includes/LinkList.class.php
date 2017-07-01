<?php
class LinkList {

	/**
	 * Constructor
	 * @private
	 */
	/* private */ function __construct() {
	}

	/**
	 * @param $status Integer: link status
	 * @param $type Integer: link type (one of Link::$link_types integers)
	 * @param $limit Integer: LIMIT for SQL query, 0 by default.
	 * @param $page Integer: used to build the OFFSET in the SQL query.
	 * @param $order String: ORDER BY clause for SQL query.
	 */
	public function getLinkList( $status, $type, $limit = 0, $page = 0, $order = 'link_submit_date' ) {
		$dbr = wfGetDB( DB_SLAVE );

		$params['ORDER BY'] = "$order DESC";
		if ( $limit ) {
			$params['LIMIT'] = $limit;
		}
		if ( $page ) {
			$params['OFFSET'] = $page * $limit - ( $limit );
		}

		if ( $type > 0 ) {
			$where['link_type'] = $type;
		}
		if ( is_numeric( $status ) ) {
			$where['link_status'] = $status;
		}

		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->select(
			array( 'link' ),
			array(
				'link_id', 'link_page_id', 'link_type', 'link_status',
				'link_name', 'link_description', 'link_url',
				'link_submitter_user_id', 'link_submitter_user_name',
				'link_submit_date', 'link_approved_date', 'link_comment_count'
			),
			$where,
			__METHOD__,
			$params
		);

		$links = array();

		foreach ( $res as $row ) {
			$linkPage = Title::makeTitleSafe( NS_LINK, $row->link_name );
			$links[] = array(
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
				'user_id' => $row->link_submitter_user_id,
				'user_name' => $row->link_submitter_user_name,
				'wiki_page' => ( ( $linkPage ) ? htmlspecialchars( $linkPage->getFullURL(), ENT_QUOTES ) : null ),
				'comments' => ( ( $row->link_comment_count ) ? $row->link_comment_count : 0 )
			);
		}

		return $links;
	}

	/**
	 * Get the number of links matching the given criteria.
	 *
	 * @param $status Integer: link status
	 * @param $type Integer: link type (one of Link::$link_types integers)
	 * @return Integer: number of links matching the given criteria
	 */
	public function getLinkListCount( $status, $type ) {
		$dbr = wfGetDB( DB_SLAVE );

		$where = array();
		if ( $type > 0 ) {
			$where['link_type'] = $type;
		}
		if ( is_numeric( $status ) ) {
			$where['link_status'] = $status;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'link',
			array( 'COUNT(*) AS count' ),
			$where,
			__METHOD__
		);

		return $s->count;
	}

}
