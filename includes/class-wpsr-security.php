<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Security {

    public static function can_manage() {
        return current_user_can('manage_options');
    }

    public static function deny_access() {
        wp_die(esc_html__('Unauthorized access.', 'trio-site-recovery'));
    }

    public static function verify_nonce($action, $field) {
        return isset($_POST[$field]) && check_admin_referer($action, $field);
    }

    public static function clean_text($value) {
        return sanitize_text_field(wp_unslash($value));
    }
}
