<?php
/*
 * LogStore_Query
 */
class LogStore_Query {

	/**
	 * List of found user ids
	 *
	 * @since 3.1.0
	 * @access private
	 * @var array
	 */
	var $results;

	/**
	 * Total number of found users for the current query
	 *
	 * @since 3.1.0
	 * @access private
	 * @var int
	 */
	var $total_items = 0;

	// SQL clauses
	var $query_fields;
	var $query_from;
	var $query_where;
	var $query_orderby;
	var $query_limit;

	var $_table;
	var $_logger;

	var $stats;

	/**
	 * PHP4 constructor
	 */
	function LogStore_Query( $query = null ) {
		$this->__construct( $query );
	}

	/**
	 * PHP5 constructor
	 *
	 * @since 3.1.0
	 *
	 * @param string|array $args The query variables
	 * @return WP_User_Query
	 */
	function __construct($logger, $query = null) {
		$this->_logger = $logger;

		if (!empty( $query)) {
			$this->query_vars = wp_parse_args($query, array(
				'blog_id' => $GLOBALS['blog_id'],
				'include' => array(),
				'exclude' => array(),
				'search' => '',
				'orderby' => 'time',
				'order' => 'DESC',
				'offset' => '',
				'number' => '',
				'count_total' => true,
				'fields' => 'all',
				'tag' => '',
				'status' => '',
			));

			$this->prepare_query();
			//$this->query();
		}
	}

	/**
	 * Prepare the query variables
	 *
	 * @since 3.1.0
	 * @access private
	 */
	function prepare_query() {
		global $wpdb;

		$this->_table = $wpdb->prefix.'logstore';

		$qv = &$this->query_vars;

		if (is_array( $qv['fields'])) {
			$qv['fields'] = array_unique( $qv['fields'] );

			$this->query_fields = array();
			foreach ($qv['fields'] as $field)
				$this->query_fields[] = $this->_table.'.'.esc_sql($field);
			$this->query_fields = implode( ',', $this->query_fields);
		} elseif ('all' == $qv['fields']) {
			$this->query_fields = $this->_table.".*";
		} else {
			$this->query_fields = $this->_table.".ID";
		}

		$this->query_from = "FROM ".$this->_table;
		$this->query_where = "WHERE ".$this->_table.".logger = '".$this->_logger."'";

		// sorting
		if (in_array($qv['orderby'], array('tag', 'time'))) {
			$orderby = $qv['orderby'];
		} else {
			$orderby = 'ID';
		}

		$qv['order'] = strtoupper($qv['order']);
		$order = ('ASC' == $qv['order']) ? 'ASC' : 'DESC';
		$this->query_orderby = "ORDER BY ".$orderby." ".$order;

		// limit
		if ($qv['number']) {
			if ($qv['offset'])
				$this->query_limit = $wpdb->prepare("LIMIT %d, %d", $qv['offset'], $qv['number']);
			else
				$this->query_limit = $wpdb->prepare("LIMIT %d", $qv['number']);
		}

		$search = trim( $qv['search'] );
		if ($search) {
			$leading_wild = (ltrim($search, '*') != $search);
			$trailing_wild = (rtrim($search, '*') != $search);
			if ($leading_wild && $trailing_wild)
				$wild = 'both';
			elseif ($leading_wild)
				$wild = 'leading';
			elseif ($trailing_wild)
				$wild = 'trailing';
			else
				$wild = false;
			if ($wild)
				$search = trim($search, '*');

			if (false !== preg_match('/[1-9][0-9]{1,3}-[0-9]{1,2}-[0-9]{1,2}/', $search))
				$search_columns = array('time');
			elseif (is_numeric($search))
				$search_columns = array('ID');
			else
				$search_columns = array('tag', 'message');

			$this->query_where .= $this->get_search_sql($search, $search_columns, $wild);
		}

		$blog_id = absint($qv['blog_id']);

		if (!empty($qv['id'])) {
			$this->query_where .= " AND ".$this->_table.".ID = '".trim($qv['id'])."'";
		} else if (!empty($qv['ID'])) {
			$this->query_where .= " AND ".$this->_table.".ID = '".trim($qv['ID'])."'";
		} else {
			if (!empty($qv['tag'])) $this->query_where .= " AND ".$this->_table.".tag = '".trim($qv['tag'])."'";

			if (!empty($qv['status'])) $this->query_where .= " AND ".$this->_table.".status = '".trim($qv['status'])."'";

			if (!isset($qv['range'])) $qv['range'] = array(date("Y-m-d h:i:s", current_time('timestamp')-86400*5));
			if (!empty($qv['range'][1])) $this->query_where .= " AND ".$this->_table.".time <= '".$qv['range'][1]."'";
			if (!empty($qv['range'][0])) $this->query_where .= " AND ".$this->_table.".time >= '".$qv['range'][0]."'";

			if (!empty($qv['include'])) {
				$ids = implode(',', wp_parse_id_list( $qv['include']));
				$this->query_where .= " AND ".$this->_table.".ID IN (".$ids.")";
			} elseif ( !empty($qv['exclude']) ) {
				$ids = implode( ',', wp_parse_id_list( $qv['exclude']));
				$this->query_where .= " AND ".$this->_table.".ID NOT IN (".$ids.")";
			}
		}

		do_action_ref_array( 'pre_log_query', array(&$this));
	}

	/**
	 * Execute the query, with the current variables
	 *
	 * @since 3.1.0
	 * @access private
	 */
	function query() {
		global $wpdb;

		$query_parts = array(
			"SELECT",
			$this->query_fields,
			$this->query_from,
			$this->query_where,
			$this->query_orderby,
			$this->query_limit
		);

		if (is_array($this->query_vars['fields']) || 'all' == $this->query_vars['fields']) {
			$this->results = $wpdb->get_results(implode(" ", $query_parts));
		} else {
			$this->results = $wpdb->get_col(implode(" ", $query_parts));
		}

		if ($this->query_vars['count_total'])
			$this->total_items = $this->get_count();

		if (!$this->results) return;
	}

	/*
	 * Used internally to generate an SQL string for searching across multiple columns
	 *
	 * @access protected
	 * @since 3.1.0
	 *
	 * @param string $string
	 * @param array $cols
	 * @param bool $wild Whether to allow wildcard searches. Default is false for Network Admin, true for
	 *  single site. Single site allows leading and trailing wildcards, Network Admin only trailing.
	 * @return string
	 */
	function get_search_sql($string, $cols, $wild = false) {
		$string = esc_sql($string);

		$searches = array();
		$leading_wild = ('leading' == $wild || 'both' == $wild) ? '%' : '';
		$trailing_wild = ('trailing' == $wild || 'both' == $wild) ? '%' : '';
		foreach ($cols as $col) {
			$searches[] = ('ID' == $col) ? $col." = '".$string."'" : $col." LIKE '".$leading_wild.like_escape($string).$trailing_wild."'";
		}

		return " AND (".implode(" OR ", $searches).")";
	}

	/**
	 * Return the list of users
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @return array
	 */
	function get_results() {
		$this->query();
		return $this->results;
	}

	/**
	 * Return the total number of users for the current query
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @return array
	 */
	function get_total() {
		return $this->total_items;
	}

	function get_count() {
		$stats = $this->get_stats();
		return array_sum((array)$stats);
	}

	function get_stats() {
		global $wpdb;

		$query_parts = array(
			"SELECT ".$this->_table.".status, COUNT(*) as num_items",
			$this->query_from,
			$this->query_where,
			"GROUP BY ".$this->_table.".status ASC"
		);

		$count = $wpdb->get_results(implode(" ", $query_parts), ARRAY_A);

		$stats = array();
		foreach ((array)$count as $row) $stats[$row['status']] = $row['num_items'];

		$this->stats = (object) $stats;

		return $this->stats;
	}
}
?>