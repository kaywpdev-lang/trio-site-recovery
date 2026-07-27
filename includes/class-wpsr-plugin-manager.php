<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Plugin_Manager {

    public static function get_active_plugins() {
        return get_option('active_plugins', array());
    }

    public static function disable_plugin($plugin_file) {
        $plugin_file = plugin_basename($plugin_file);

        // Never allow WP Site Recovery to disable itself.
        if (defined('WPSR_FILE') && $plugin_file === plugin_basename(WPSR_FILE)) {
            WPSR_Logger::add(
                'plugin_disable_blocked',
                array(
                    'plugin' => $plugin_file,
                    'reason' => 'Attempted to disable WP Site Recovery.'
                )
            );

            return false;
        }

        $active_plugins = get_option('active_plugins', array());

        if (!is_array($active_plugins) || !in_array($plugin_file, $active_plugins, true)) {
            return false;
        }

        $new_plugins = array_values(
            array_diff($active_plugins, array($plugin_file))
        );

        update_option('active_plugins', $new_plugins);

        WPSR_Logger::add(
            'plugin_disabled',
            array(
                'plugin' => $plugin_file,
            )
        );

        return true;
    }
}