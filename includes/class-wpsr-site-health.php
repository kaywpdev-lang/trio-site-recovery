<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Site_Health {

    public static function get_status() {
        return array(
            'wordpress_version' => get_bloginfo('version'),
            'php_version'       => PHP_VERSION,
            'theme'             => wp_get_theme()->get('Name'),
            'plugins_count'     => count(get_option('active_plugins', array())),
            'memory_limit'      => WP_MEMORY_LIMIT,
            'debug_mode'        => defined('WP_DEBUG') && WP_DEBUG,
            'site_url'          => site_url(),
            'home_url'          => home_url(),
        );
    }
}
