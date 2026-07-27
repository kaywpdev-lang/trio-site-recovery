=== Trio Site Recovery ===
Contributors: triosis
Tags: recovery, safe mode, debug, error log, site health
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover common WordPress frontend issues directly from wp-admin.

== Description ==

Trio Site Recovery helps administrators recover a broken WordPress frontend when wp-admin is still accessible.

The plugin provides recovery tools for common WordPress issues without requiring FTP, cPanel, SSH, or direct database access.

Features include:

* Site Health Dashboard
* Emergency Recovery
* Safe Mode
* Snapshot Creation
* Restore Latest Snapshot
* Theme Recovery
* Quick Plugin Disable
* Runtime Debug Manager
* Error Log Viewer
* Recovery History

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Site Recovery in the admin menu.

== Frequently Asked Questions ==

= Does this plugin edit wp-config.php? =

No. Runtime debug logging does not modify wp-config.php.

= Does this plugin require FTP access? =

No. The plugin is designed to work from wp-admin.

= What does Emergency Recovery do? =

It creates a snapshot, disables active plugins, and switches to an available default WordPress theme.

== Changelog ==

= 1.0.0 =
* Initial release.