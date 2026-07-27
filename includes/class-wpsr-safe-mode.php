<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Safe_Mode {

    public static function enable() {
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

        WPSR_Logger::add(
            'safe_mode_enabled',
            'Safe Mode enabled. All plugins disabled except WP Site Recovery.'
        );

        return true;
    }
}