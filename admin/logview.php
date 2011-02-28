<?php
$id = (isset($_REQUEST['id'])) ? $_REQUEST['id'] : null;
$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : null;
$page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : '';
$uri = remove_query_arg(array('action', 'action2', 'id', '_wpnonce', 'errors', 'messages'));;

$title = __($this->_title.' Log');

switch ($action) {
	case "view":
		if ($id) {
			$entries = $this->query_log(array('ID' => $id));
			$entry = $this->run_entry_filter($entries[0]);
			?>
			<div class="wrap">
				<?php screen_icon(); ?>
				<h2><?php printf(__('%s log entry from %s'), esc_html($this->_title), mysql2date(get_option('date_format'), $entry->time)); ?></h2>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">Status</th>
							<td><p><?php echo $entry->status; ?></p></td>
						</tr>
						<tr>
							<th scope="row">Message</th>
							<td><p><?php echo $entry->message; ?></p></td>
						</tr>
						<tr>
							<th scope="row">Data</th>
							<td><?php echo apply_filters('logstore_format_entry_data-'.$this->_name, $this->format_data($entry->data)); ?></td>
						</tr>
						<tr>
							<th scope="row">Raw Data</th>
							<td><textarea class="large-text" rows="5"><?php echo $entry->data; ?></textarea></td>
						</tr>
						<tr>
							<th scope="row">Tag</th>
							<td><p><?php echo $entry->tag; ?></p></td>
						</tr>
					</tbody>
				</table>
				<a href="<?php echo wp_get_referer(); ?>" class="button-secondary">back</a>
			</div>
			<?php
		}
		break;
	case "clearlog";
		if (empty($_REQUEST['confirmation'])) {
			?>
			<div class="wrap">
				<?php screen_icon(); ?>
				<h2><?php echo esc_html($title); ?></h2>
				<p>Are you sure you want to delete all entries in this log?</p>
				<form method="POST" action="<?php echo $uri; ?>">
					<?php wp_nonce_field('clearlog-'.$this->_name); ?>
					<input type="hidden" name="action" value="clearlog" />
					<input type="hidden" name="confirmation" value="1" />
					<div class="alignleft actions">
						<input type="submit" class="button-primary" value="Yes, clear the log." />
						<a href="<?php echo $uri; ?>" class="button-secondary">No, take me back.</a>
					</div>
				</form>
			</div>
			<?php
		}
		break;
	default:
		require_once(dirname(__FILE__).'/includes/class-logstore-list-table.php');
		$list_table = new LogStore_List_Table(&$this, $uri);

		$list_table->prepare_items();
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2>
				<?php echo esc_html($title); ?>
				<?php
				if (isset($_REQUEST['s']) && $_REQUEST['s'])
					printf( '<span class="subtitle">' . __('Search results for &#8220;%s&#8221;') . '</span>', get_search_query() );
				?>
			</h2>

			<?php $list_table->views(); ?>

			<form id="log-filter" action="<?php echo $_SERVER['PHP_SELF'] ?>" method="get">
				<input type="hidden" name="page" value="<?php echo $page; ?>" />
				<?php $list_table->search_box( __('Search Log'), 'logstore' ); ?>
				<?php $list_table->display(); ?>
				<div id="ajax-response"></div>
				<br class="clear" />
			</form>
		</div>
		<?php
	break;
}
?>