<?php
/*
Plugin Name: LogStore Plus
Description: LogStore is a 'meta' plugin that allows other plugins to log data easily
Author: Andre Wyrwa <andre@wyrfel.com>, Team Markitekt <leho@markitekt.ee>
Version: 0.1.3
*/

/*
Copyright 2010  Andre Wyrwa (email : andre@wyrfel.com)
*/

global $wp_version;

if (version_compare($wp_version, "3.1", "<")) {
	$exit_msg = 'This requires WordPress 3.1 or newer. <a href="http://codex.wordpress.org/Upgrading_WordPress">Please update!</a>';
	exit ($exit_msg);
}

if (!class_exists('LogStore')) {
	class LogStore {
		public $_name = '';
		public $_title = '';
		private $_table = '';
		private $_active = false;

		function LogStore($name = '', $title = '') {
			global $wpdb;
			$this->_table = $table_name = $wpdb->prefix.'logstore';

			if (empty($name)) {
				if (!defined('LOGSTORE_DIR')) {

					define('LOGSTORE_URL', plugins_url('', __FILE__));
					define('LOGSTORE_DIR', dirname(__FILE__));
					define('LOGSTORE_STATUS_OK', 'ok');
					define('LOGSTORE_STATUS_NONE', 'none');
					define('LOGSTORE_STATUS_WARN', 'warn');
					define('LOGSTORE_STATUS_CRITICAL', 'critical');
					define('LOGSTORE_STATUS_FATAL', 'fatal');

					register_activation_hook(__FILE__, array(&$this, 'activate'));
					register_deactivation_hook(__FILE__, array(&$this, 'deactivate'));

					add_action('plugins_loaded', array(&$this, '_init'), 1);
				} else {
					return false;
				}
			} else {
				$this->_name = $name;
				$this->_title = $title;
				$this->_init();
			}
		}

		function _init() {
			$prio = (empty($this->_name)) ? 10 : 50;
			add_action('init', array(&$this, 'init'), $prio);
			add_action('admin_init', array(&$this, 'admin_init'), $prio);
			add_action('admin_menu', array(&$this, 'admin_menu'), $prio);

			if (!empty($this->_name)) {
				add_filter('logstore_loggers', array(&$this, 'register_self'));
			}
		}

		function activate() {
			return $this->setup_db();
		}

		function deactivate() {
		}

		function setup_db() {
			require_once(ABSPATH.'wp-admin/includes/upgrade.php');
			global $wpdb;

			//if ($wpdb->get_var("SHOW TABLES LIKE '".$this->_table."'") == $this->_table) return true;

			$sql = "CREATE TABLE ".$this->_table." (
					ID bigint(20) NOT NULL AUTO_INCREMENT,
					logger varchar(100) NOT NULL,
					time datetime NOT NULL,
					tag varchar(100) NOT NULL,
					message text NOT NULL,
					data text NOT NULL,
					status varchar(15) DEFAULT 'none' NOT NULL,
					PRIMARY KEY  (id),
					KEY logger (logger,time),
					KEY status (status)
					);";

			if (!dbDelta($sql)) wp_die('Error: Table '.$this->_table.' not added!');
		}

		function init() {
			if (!empty($this->_name)) {
				$options = get_option('logstore');
				$this->_active = (!empty($options[$this->_name]));
				do_action('logstore_init-'.$this->_name, $this->_active);
			}
		}

		function get_admin_page_uri() {
			return remove_query_arg(array('action', 'action2', 'id', '_wpnonce', 'errors', 'messages'));
		}

		function admin_menu() {
			if (empty($this->_name)) {
				$this->_hook = add_menu_page(__('Logs'), __('Logs'), 'manage_options', 'logstore', array(&$this, 'render_page'));
			} else {
				$this->_hook = add_submenu_page('logstore', $this->_title, $this->_title, 'manage_options', 'logstore-'.$this->_name, array(&$this, 'render_page'));
				add_filter('manage_'.$this->_hook.'_columns', array(&$this, 'get_logview_columns'));
			}
		}

		function admin_init() {
			$this->setup_options();
			if (empty($this->_name)) {
				wp_register_style('logstore_logview', plugins_url('/admin/css/logview.css', __FILE__));
				add_action('admin_print_styles', array(&$this, 'enqueue_admin_styles'));
				add_action('admin_print_styles-'.$this->_hook, array(&$this, 'enqueue_logview_styles'));
			} else {
				add_action('admin_print_styles-'.$this->_hook, array(&$this, 'enqueue_logview_styles'));
				add_action('load-'.$this->_hook, array(&$this, 'handle_admin_actions'));
			}
		}

		function handle_admin_actions() {
			if (isset($_REQUEST['action'])) {
				$vars = array();
				if (!empty($this->_name)) {
					switch ($_REQUEST['action']) {
						case "delete":
							if ($_REQUEST['id'] && check_admin_referer('delete-log_entry_'.$_REQUEST['id'])) {
								global $wpdb;
								$sql = "DELETE FROM ".$this->_table." WHERE ".$this->_table.".logger = '".$this->_name."' AND ID = ".$_REQUEST['id'];
								$wpdb->query($sql);
							}
							$vars[] = 'id';
							break;
						case "clearlog";
							if ($_REQUEST['confirmation']) {
								if (check_admin_referer('clearlog-'.$this->_name)) {
									global $wpdb;
									$sql = "DELETE FROM ".$this->_table." WHERE ".$this->_table.".logger = '".$this->_name."'";
									$wpdb->query($sql);
								}
								$vars[] = 'confirmation';
							}
							break;
					}
				}
				if (count($vars)) {
					$vars[] = 'action';
					$vars[] = '_wpnonce';
					wp_redirect(remove_query_arg($vars));
					exit;
				}
			}
		}

		/*
		 * BEGIN Options
		 */

		function setup_options() {
			if (empty($this->_name)) {
				register_setting('logstore', 'logstore', array(&$this, 'validate'));
				add_settings_section('logstore-loggers', __('Available Logs'), array(&$this, 'options_intro_loggers'), 'logstore');
			} else {
				add_settings_field($this->_name, $this->_title, array(&$this, 'render_field'), 'logstore', 'logstore-loggers');
			}
		}

		function options_intro_loggers() {
			_e('Activate or deactivate system logs. This enables or disables the logging itself, already logged entries can be viewed at any time.');
		}

		function validate($input) {
			return $input;
		}

		function render_field() {
			if (!empty($this->_name)) {
				$checked = ($this->_active) ? "checked=\"checked\"" : "";
				?>
				<p>
					<label>
						<input type="checkbox" name="logstore[<?php echo $this->_name; ?>]" value="1" <?php echo $checked; ?> />
						<?php printf(__('Log %s entries.'), $this->_title); ?>
					</label>
					<a href="<?php echo admin_url('admin.php?page=logstore-'.$this->_name); ?>" class="button-secondary">view log</a>
				</p>
				<?php
			}
		}

		/*
		 * END Options
		 */

		function enqueue_admin_styles() {
			wp_enqueue_style('logstore_admin', plugins_url('/admin/css/admin.css', __FILE__));
		}

		function enqueue_logview_styles() {
			wp_enqueue_style('logstore_logview');
		}

		function enqueue_logview_scripts() {
			wp_enqueue_script('wp-ajax-response');
			wp_enqueue_script('jquery-ui-draggable');
			wp_enqueue_script('posts');
		}

		function get_logview_columns($columns) {
			$columns = array(
				"cb" => "<input type=\"checkbox\" />",
				"date" => "Time",
			//	"tags" => "Tag",
				"message" => "Message",
			//	"data" => "Data",
			);
			return $columns;
		}

		function render_page() {
			if (empty($this->_name)) {
				?>
				<div class="wrap">
					<?php echo screen_icon(); ?>
					<h2><?php _e('Logs'); ?></h2>
					<p><?php _e('For each log registered by one of your active plugins, you will find a checkbox below. Use the checkbox to activate or deactivate logging for that particular log.'); ?></p>
					<form action="options.php" method="post">
						<?php
						settings_fields('logstore');
						do_settings_sections('logstore');
						?>
						<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
					</form>
				</div>
				<?php
			} else {
				include(dirname(__FILE__).'/admin/logview.php');
			}
		}

		function register_self($loggers) {
			if ($this->_name) $loggers[$this->_name] = &$this;
			return $loggers;
		}

		function log($message = '', $data = null, $tag = '', $status = 'none') {
			global $wpdb;

			$time = current_time('mysql');
			$values = apply_filters('logstore_new_entry-'.$this->_name, compact("time", "message", "data", "tag", "status"));
			$values['data'] = maybe_serialize($values['data']);
			$values['logger'] = $this->_name;

			if ($this->_name && $this->_active) {
				$wpdb->insert($this->_table, $values);
			}
		}

		function run_entry_filter($entry) {
			$id = $entry->ID;
			unset($entry->logger);
			$entry = apply_filters('logstore_entry-'.$this->_name, (array)$entry);
			$entry = (object)$entry;
			$entry->ID = $id;
			return $entry;
		}

		function get_cycle_where($cycle) {
			return " AND ".$this->_table.".post_date >= '".$cycle[0]."' AND ".$this->_table.".post_date <= '".$cycle[1]."' ";
		}

		function get_weekrange($time) {
			$a = get_weekstartend($time);
			return array( date('Y-m-d', $a['start']), date('Y-m-d', $a['end'] ));
		}

		function get_monthrange($time) {
			$ym = date("Y-m", strtotime($time));
			$start = $ym."-01";
			$ym = explode("-", $ym);
			if ($ym[1] == 12) {
				$ym[0]++; $ym[1] = 1;
			} else {
				$ym[1]++;
			}
			$d = mktime( 0, 0, 0, $ym[1], 1, $ym[0] );
			$d -= 86400;
			$end = date("Y-m-d", $d);
			return array( $start, $end );
		}

		function get_yearrange($time) {
			$y = date('Y', strtotime($time));
			return array( $y.'-01-01', $y.'-12-31');
		}

		function get_cycle_range($cycle, $time = 0) {
			if ($time == 0) $time = current_time('mysql');
			$ts = strtotime($time);
			switch ($cycle) {
				case "td":
					return array(date("Y-m-d", strtotime($time)).' 00:00:00', date("Y-m-d", strtotime($time)).' 23:59:59');
					break;
				case "ld":
					$yesterday = date("Y-m-d", $ts-86400);
					return array($yesterday.' 00:00:00', $yesterday.' 23:59:59');
					break;
				case "tw":
					return $this->get_weekrange($time);
					break;
				case "lw":
					$lw = date("Y-m-d", $ts - 604800);
					return $this->get_weekrange($lw);
					break;
				case "tm":
					return $this->get_monthrange($time);
					break;
				case "lm":
					$mr = $this->get_monthrange($time);
					$lm = date("Y-m-d", strtotime($rm[0])-86400);
					return $this->get_yearrange($lm);
					break;
				case "ty":
					return $this->get_yearrange($time);
					break;
				case "ly":
					$ly = mysql2date("Y", $time)-1;
					return $this->get_yearrange($ly);
					break;
			}
		}

		function range_to_cycles($start, $end, $cycle) {
			$cur = $start;
			$i = 0;
			while ($cur <= $end && $i < 100) {
				$range = $this->get_cycle_range($cycle, $cur);
				$cur = date('Y-m-d', strtotime($range[1])+86400);
				$i++;
			}
			return $i;
		}

        function xml_highlight($s) {
            $s = htmlspecialchars($s, ENT_COMPAT | ENT_HTML401, "UTF-8" , false);
            $s = preg_replace("#&lt;([/]*?)(.*)([\s]*?)&gt;#sU",
                    "<font color=\"#0000FF\">&lt;\\1\\2\\3&gt;</font>",$s);
            $s = preg_replace("#&lt;([\?])(.*)([\?])&gt;#sU",
                    "<font color=\"#800000\">&lt;\\1\\2\\3&gt;</font>",$s);
            $s = preg_replace("#&lt;([^\s\?/=])(.*)([\[\s/]|&gt;)#iU",
                    "&lt;<font color=\"#808000\">\\1\\2</font>\\3",$s);
            $s = preg_replace("#&lt;([/])([^\s]*?)([\s\]]*?)&gt;#iU",
                    "&lt;\\1<font color=\"#808000\">\\2</font>\\3&gt;",$s);
            $s = preg_replace("#([^\s]*?)\=(&quot;|')(.*)(&quot;|')#isU",
                    "<font color=\"#800080\">\\1</font>=<font color=\"#FF00FF\">\\2\\3\\4</font>",$s);
            $s = preg_replace("#&lt;(.*)(\[)(.*)(\])&gt;#isU",
                    "&lt;\\1<font color=\"#800080\">\\2\\3\\4</font>&gt;",$s);
            return nl2br($s);
        }

        function xml_indent($xml) {
            // add marker linefeeds to aid the pretty-tokeniser (adds a linefeed between all tag-end boundaries)
            $xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);

            // now indent the tags
            $token      = strtok($xml, "\n");
            $result     = ''; // holds formatted version as it is built
            $pad        = 0; // initial indent
            $matches    = array(); // returns from preg_matches()

            // scan each line and adjust indent based on opening/closing tags
            while ($token !== false) :
            // test for the various tag states
            // 1. open and closing tags on same line - no change
                if (preg_match('/.+<\/\w[^>]*>$/', $token, $matches)) :
                    $indent=0;
                    // 2. closing tag - outdent now
                elseif (preg_match('/^<\/\w/', $token, $matches)) :
                    $pad--;
                    // 3. opening tag - don't pad this one, only subsequent tags
                elseif (preg_match('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) :
                    $indent=1;
                    // 4. no indentation needed
                else :
                    $indent = 0;
                endif;

                // pad the line with the required number of leading spaces
                $line    = str_pad($token, strlen($token)+$pad, ' ', STR_PAD_LEFT);
                $result .= $line . "\n"; // add to the cumulative result, with linefeed
                $token   = strtok("\n"); // get the next token
                $pad    += $indent; // update the pad size for subsequent lines
            endwhile;

            return $result;
        }


		function format_data($data) {
			if (is_serialized($data)) {
				$data = unserialize($data);
				return $this->format_data($data);
			} else if (is_array($data)) {
				$output = "<dl class=\"logdata\">";
				$i = 0;
				foreach ($data as $k => $v) {
					$alt = (++$i % 2 == 1) ? "class=\"alternate\"" : "";
					if (is_array($v)) $v = $this->format_data($v);
					$output .= "<dt ".$alt.">".$k.":<dt>";
                    if (!empty($v))
                        $v = strpos($v, "?xml") ? $this->xml_highlight($this->xml_indent($v)) : $v;

                    $output .= "<dd ".$alt.">".($v ? $v : "&nbsp;")."<dd>";
				}
				$output .= "</dl>";
				return $output;
			} else {
                if (strpos($data, "?xml"))
                    $data = $this->xml_highlight($this->xml_indent($data));

				return "<div class=\"logdata\">".$data."</div>";
			}
		}

		function query_log($q) {
			require_once(dirname(__FILE__).'/includes/query.php');
			$query = new LogStore_Query($this->_name, $q);
			return $query->get_results();
		}
	}
	if (is_admin()) new LogStore();
}
?>
