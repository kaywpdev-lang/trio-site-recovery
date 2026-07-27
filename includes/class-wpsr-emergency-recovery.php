<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Emergency_Recovery {

    public function recover() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'trio-site-recovery'));
        }

        WPSR_Snapshots::create();

        $active_plugins = get_option('active_plugins', array());

        if (!is_array($active_plugins)) {
            $active_plugins = array();
        }

        $current_plugin_file = defined('WPSR_FILE') ? plugin_basename(WPSR_FILE) : '';
        $remaining_plugins   = array();

        foreach ($active_plugins as $plugin_file) {
            if (!empty($current_plugin_file) && $plugin_file === $current_plugin_file) {
                $remaining_plugins[] = $plugin_file;
            }
        }

        update_option('active_plugins', $remaining_plugins);

        $fallback_themes = array(
            'twentytwentyfive',
            'twentytwentyfour',
            'twentytwentythree',
        );

        $switched_theme = '';

        foreach ($fallback_themes as $theme_slug) {
            if (wp_get_theme($theme_slug)->exists()) {
                switch_theme($theme_slug);
                $switched_theme = $theme_slug;
                break;
            }
        }

        WPSR_Logger::add(
            'emergency_recovery',
            'Emergency recovery executed. Plugins disabled except WP Site Recovery. Fallback theme: ' . $switched_theme
        );

        return true;
    }
}