<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_DB {

    public static function activate() {
        self::create_tables();

        if (class_exists('WPSR_Logger')) {
            WPSR_Logger::add('plugin_activated', 'WP Site Recovery plugin activated.');
        }
    }

    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $logs_table      = $wpdb->prefix . 'sr_logs';
        $snapshots_table = $wpdb->prefix . 'sr_snapshots';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql_logs = "CREATE TABLE {$logs_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            action VARCHAR(255) NOT NULL,
            details LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        $sql_snapshots = "CREATE TABLE {$snapshots_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            plugins LONGTEXT NULL,
            theme VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        dbDelta($sql_logs);
        dbDelta($sql_snapshots);
    }
}
