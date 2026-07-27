<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Snapshots {

    public static function create() {
        global $wpdb;

        $table   = $wpdb->prefix . 'sr_snapshots';
        $plugins = get_option('active_plugins', array());
        $theme   = get_stylesheet();

        $wpdb->insert(
            $table,
            array(
                'plugins'    => wp_json_encode($plugins),
                'theme'      => sanitize_text_field($theme),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s')
        );

        WPSR_Logger::add('snapshot_created', array(
            'theme'         => $theme,
            'plugins_count' => count($plugins),
        ));

        return $wpdb->insert_id;
    }

    public static function get_latest() {
        global $wpdb;

        $table = $wpdb->prefix . 'sr_snapshots';

        return $wpdb->get_row("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 1");
    }

    public static function get_all($limit = 10) {
        global $wpdb;

        $table = $wpdb->prefix . 'sr_snapshots';
        $limit = absint($limit);

        if ($limit < 1) {
            $limit = 10;
        }

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit)
        );
    }

    public static function restore_latest() {
        $snapshot = self::get_latest();

        if (!$snapshot) {
            return false;
        }

        $plugins = json_decode($snapshot->plugins, true);

        if (is_array($plugins)) {
            update_option('active_plugins', $plugins);
        }

        if (!empty($snapshot->theme) && wp_get_theme($snapshot->theme)->exists()) {
            switch_theme($snapshot->theme);
        }

        WPSR_Logger::add('snapshot_restored', array(
            'snapshot_id' => $snapshot->id,
            'theme'       => $snapshot->theme,
        ));

        return true;
    }
}
