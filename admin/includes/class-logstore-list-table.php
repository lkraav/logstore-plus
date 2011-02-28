<?php
/**
 * LogStore List Table class.
 *
 * @package LogStore
 * @subpackage List_Table
 * @since 3.1.0
 * @access private
 */
class LogStore_List_Table extends WP_List_Table {
	private $_p = null;
	private $_uri = '';
	private $query = null;

	function LogStore_List_Table($parent) {
		$this->_p = $parent;
		$this->_uri = $this->_p->get_admin_page_uri();

		parent::WP_List_Table( array(
			'plural' => 'logs',
			'singular' => 'log',
			'ajax' => false,
		) );
	}

	function ajax_user_can() {
		return current_user_can('manage_options');
	}

	function prepare_items() {
		require_once(dirname(__FILE__).'/../../includes/query.php');
		$search = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';

		$status = isset( $_REQUEST['role'] ) ? $_REQUEST['role'] : '';

		$items_per_page = $this->get_items_per_page('items_per_page');

		$paged = $this->get_pagenum();

		$args = array(
			'number' => $items_per_page,
			'offset' => ($paged-1)*$items_per_page,
			'search' => '*'.$search.'*',
			'fields' => 'all',
		);

		if ( isset( $_REQUEST['orderby'] ) )
			$args['orderby'] = $_REQUEST['orderby'];

		if ( isset( $_REQUEST['order'] ) )
			$args['order'] = $_REQUEST['order'];

		if (isset($_REQUEST['period']))
			$args['range'] = $this->_p->get_cycle_range($_REQUEST['period']);

		// Query the user IDs for this page
		$this->query = new LogStore_Query($this->_p->_name, $args);

		$this->rows = $this->query->get_results();

		$this->set_pagination_args( array(
			'total_items' => $this->query->get_total(),
			'per_page' => $items_per_page,
		) );
	}

	function get_views() {
		global $wpdb, $post_mime_types, $avail_post_mime_types;

		?>
		<ul class="subsubsub">
			<?php
			$stati = array('ok' => 'Ok', 'none' => 'Normal', 'warn' => 'Warning', 'critical' => 'Critical', 'fatal' => 'Fatal');
			$status_links = array();
			$num_entries = $this->query->stats;
			$class = '';
			$allposts = '';
			$link_uri = remove_query_arg('status', $this->_uri);
			$total_entries = $this->query->get_total();
			$class = empty($class) && empty($_GET['status']) ? ' class="current"' : '';
			$status_links[] = "<li><a href='".$link_uri."'".$class.">".sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_entries, 'posts'), number_format_i18n($total_entries)).'</a>';

			foreach ($stati as $status => $status_name) {
				if (empty($num_entries->$status)) continue;
				$class = (isset($_GET['status']) && $status == $_GET['status'] ) ? ' class="current"' : '';
				$status_links[] = "<li><a href='".$link_uri."&amp;status=".$status."'".$class.">".sprintf( __($status_name.' <span class="count">(%s)</span>'), number_format_i18n($num_entries->$status)).'</a>';
			}
			echo implode( " |</li>\n", $status_links ) . '</li>';
			unset($status_links);
			?>
		</ul>
		<?php
	}

	function get_bulk_actions() {
		$actions = array();
		$actions['delete'] = __( 'Delete Permanently' );
		return $actions;
	}

	function extra_tablenav( $which ) {
		?>
		<div class="alignleft actions">
			<?php
			if ('top' == $which && !is_singular()) {
				$default = (empty($_GET['period'])) ? 'all' : $_GET['period'];
				$this->periods_dropdown($default);
				do_action('restrict_manage_logs');
				submit_button( __( 'Filter' ), 'secondary', false, false, array('id' => 'log-query-submit'));
			}
			?>
			<br class="clear" />
		</div>
		<?php
		if ('bottom' == $which) {
			?>
			<div class="alignleft actions">
				<a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'clearlog')), 'clearlog'); ?>" class="button-secondary">Clear Log</a>
			</div>
			<?php
		}
	}

	function current_action() {
		if ( isset( $_REQUEST['find_detached'] ) )
			return 'find_detached';

		if ( isset( $_REQUEST['found_post_id'] ) && isset( $_REQUEST['media'] ) )
			return 'attach';

		if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) )
			return 'delete_all';

		return parent::current_action();
	}

	function has_items() {
		return count($this->rows);
	}

	function no_items() {
		_e( 'No log entries found.' );
	}

	function get_columns() {
		$columns = array(
			"cb" => "<input type=\"checkbox\" />",
			"date" => "Time",
		//	"tags" => "Tag",
			"message" => "Message",
		//	"data" => "Data",
		);
		$columns = apply_filters( 'manage_logger_columns', $columns, $this->_name, $this->_title);

		return $columns;
	}

	function get_sortable_columns() {
		return array(
			'date' => array( 'time', true ),
		);
	}

	function display_rows() {
		global $current_user;
		add_filter('the_title','esc_html');
		$alt = '';
		$hidden = get_hidden_columns('upload');

		foreach ($this->rows as $entry) {
			// we want to allow filtering for output, however, we need to apply some safety so plugins can't mess with the ID or logger
			$entry = $this->_p->run_entry_filter($entry);

			$att_title = '';

			$alt = ('alternate' == $alt) ? '' : 'alternate';
			?>
			<tr id='post-<?php echo $id; ?>' class='<?php echo trim($alt.' status-'.$entry->status); ?>' valign="top">
				<?php
				list( $columns, $hidden ) = $this->get_column_info();
				foreach ($columns as $column_name => $column_display_name ) {
					$class = "class=\"$column_name column-$column_name\"";
					$style = '';
					if ( in_array($column_name, $hidden) ) $style = ' style="display:none;"';
					$attributes = "$class$style";

					switch($column_name) {
						case 'cb':
							?>
							<th scope="row" class="check-column">
								<input type="checkbox" name="logentry[]" value="<?php $entry->ID; ?>" />
							</th>
							<?php
							break;
						case 'tags':
							?>
							<td <?php echo $attributes ?>>
								<?php echo $entry->tag; ?>
							</td>
							<?php
							break;
						case 'message':
							?>
							<td <?php echo $attributes ?>>
								<p>
									<?php echo wp_trim_excerpt($entry->message); ?>
								</p>
								<?php
								echo $this->row_actions($this->_get_row_actions($entry, ''));
								?>
							</td>
							<?php
							break;
						case 'date':
							if ('0000-00-00 00:00:00' == $entry->time && 'date' == $column_name) {
								$t_time = $h_time = __('Unpublished');
							} else {
								$time = strtotime($entry->time);
								if ((abs($t_diff = time() - $time)) < 86400) {
									if ($t_diff < 0)
										$h_time = sprintf(__('%s from now'), human_time_diff($time));
									else
										$h_time = sprintf(__('%s ago'), human_time_diff($time));
								} else {
									$h_time = mysql2date(__('Y/m/d h:i:s'), $entry->time);
								}
							}
							?>
							<td <?php echo $attributes; ?>>
								<?php echo $h_time; ?>
							</td>
							<?php
							break;

						case 'actions':
							?>
							<td <?php echo $attributes ?>>
							<a href="media.php?action=edit&amp;attachment_id=<?php the_ID(); ?>" title="<?php echo esc_attr(sprintf(__('Edit &#8220;%s&#8221;'), $att_title)); ?>"><?php _e('Edit'); ?></a> |
							<a href="<?php the_permalink(); ?>"><?php _e('Get permalink'); ?></a>
							</td>
							<?php
							break;

						default:
							?>
							<td <?php echo $attributes ?>><?php do_action('manage_logger_custom_column', $column_name, $id); ?></td>
							<?php
							break;
					}
				}
				?>
			</tr>
			<?php
		}
	}

	function _get_row_actions($entry, $att_title) {
		$actions = array();
		$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url(add_query_arg(array('action' => 'delete', 'id' => $entry->ID), $this->_uri), 'delete-log_entry_'.$entry->ID) . "'>".__('Delete Permanently')."</a>";
		if (!empty($entry->data)) $actions['view'] = "<a href='".wp_nonce_url(add_query_arg(array('action' => 'view', 'id' => $entry->ID), $this->_uri), 'view-log_entry_'.$entry->ID) . "'>".__('View')."</a>";

		return $actions;
	}

	function periods_dropdown($selected) {
		$periods = array(
			'td' => 'Today',
			'ld' => 'Yesterday',
			'tw' => 'This Week',
			'lw' => 'Last Week',
			'tm' => 'This Month',
			'lm' => 'Last Month',
			'ty' => 'This Year',
		);
		?>
		<select name='period'>
			<option value="all"><?php _e('Show all'); ?></option>
			<?php
			foreach ($periods as $period => $pname) {
				printf( "<option %s value='%s'>%s</option>\n", selected($selected, $period, false), esc_attr($period), __($pname));
			}
			?>
		</select>
		<?php
	}
}

?>
