=== LogStore ===
Contributors: wyrfel
Tags: logging, log, development, meta
Requires at least: 3.1
Tested up to: 3.1
Stable tag: 0.1.2

LogStore is a 'meta' plugin that allows other plugins to log data easily.

== Description ==

LogStore can be used to log (important) data from within your plugin.
It provides a simple interface to log entries and a friendly administration interface for viewing and managing the log entries.

While LogStore can be used to log debug output or system events, it's main intention is to log more extensive data, such as:

* system emails sent your users
* data received from remote API calls

== Installation ==

1. Upload the `logstore` folder to the `/wp-content/plugins/` directory

== Frequently Asked Questions ==

None, yet.

== Screenshots ==

1. This is LogStore's main admin screen. Here the user can activate or deactivate the logging for individual loggers.
2. This is LogStore's admin menu. A submenu entry is created for each individual registered logger, no matter if active or not.
3. This is the log view for a logger. Individual entries can be deleted, it can be sorted, uses pagination and the whole log can be wiped.
4. This is the log entry view where you can view the individual log entries. The data is presented both in a nicely formatted and in raw form.

== Changelog ==

= 0.1.2 =
* Readme fixes,
* move to new repository

= 0.1.1 =
* Readme fixes

= 0.1 =
* Initial release

== Upgrade Notice ==

= 0.1 =
Initial Release.

== Usage ==

To use LogStore in your plugin, add the following line to your plugin's 'plugins_loaded' callback function:

`if (class_exists('LogStore')) $this->log = new LogStore('slug', __('Title'));`

* The 'slug' should be a unique identifier for your logger.
* The 'Title' will be the title used in menu entries, options and page titles.

Once that's done you can then start logging your data from anywhere within your plugin by using the following:

`if (!empty($this->log)) $this->log->log(__('My log message'), $my_log_data, 'status', 'tag');`

* 'My log message.' is any arbitrary message to describe your log entry.
* $my_log_data is the extended data you want to log. This can be either an array or string.
* 'status' is one of 'none', 'ok', 'warn', 'critical' or 'fatal'.
* 'tag' can be any kind of singular tag to  further classification to your log entries.add

If you use LogStore in your plugin, you should check for it's presence in

* your 'plugins_loaded' callback
* your plugin activation callback

You should either make LogStore use optional or notify the user that they need to install LogStore for your plugin to work properly.

== Plugin Hooks ==

In the following hooks, `*myname*` stands for the name with which you instanciated the LogStore class.

= Action Hooks =

* `logstore_init-*myname*` - runs during the execution of WP's `init` action hook, passes a single boolean parameter indicating if logging is active or inactive for this logger

= Filter Hooks =

* `logstore_new_entry-*myname*` - runs during the creation of a new entry, passes a single array containing all values for that entry ('time', 'message', 'data', 'status' and 'tag')
* `logstore_entry-*myname*` - runs before displaying an entry on screen, passes a single array containting all values for the entry ('time', 'message', 'data', 'status' and 'tag')
* `logstore_format_entry_data-*myname*` - runs before displaying the formatted data on screen, passes the formatted data as string

== Further Notes ==
Please note that the 'bulk actions' in the log viewer are currently not
working.
