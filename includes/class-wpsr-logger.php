<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Logger {

    public static function add($action, $details = '') {
        global $wpdb;

        $table = $wpdb->prefix . 'sr_logs';

        if (is_array($details) || is_object($details)) {
            $details = wp_json_encode($details);
        }

        $wpdb->insert(
            $table,
            array(
                'user_id'    => get_current_user_id(),
                'action'     => sanitize_text_field($action),
                'details'    => sanitize_textarea_field((string) $details),
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s')
        );
    }

    public static function get_logs($limit = 20) {
        global $wpdb;

        $table = $wpdb->prefix . 'sr_logs';
        $limit = absint($limit);

        if ($limit < 1) {
            $limit = 20;
        }

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit)
        );
    }
}
