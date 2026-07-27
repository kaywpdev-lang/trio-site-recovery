<?php
/**
 * Uninstall WP Site Recovery.
 *
 * @package WP_Site_Recovery
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$logs_table      = $wpdb->prefix . 'sr_logs';
$snapshots_table = $wpdb->prefix . 'sr_snapshots';

$wpdb->query("DROP TABLE IF EXISTS {$logs_table}");
$wpdb->query("DROP TABLE IF EXISTS {$snapshots_table}");

delete_option('wpsr_runtime_debug_enabled');