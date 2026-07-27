<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPSR_Debug_Manager {

    const OPTION_KEY = 'wpsr_runtime_debug_enabled';

    /**
     * Get current debug status.
     */
    public static function get_status() {
        return array(
            'wp_debug'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'wp_debug_log'  => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
            'script_debug'  => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
            'savequeries'   => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
            'runtime_debug' => self::is_runtime_debug_enabled(),
        );
    }

    /**
     * Enable runtime debug.
     */
    public static function enable_runtime_debug() {

        update_option( self::OPTION_KEY, 1 );

        if ( class_exists( 'WPSR_Error_Viewer' ) ) {
            WPSR_Error_Viewer::create_log_file();
        }

        WPSR_Logger::add(
            'runtime_debug_enabled',
            'Runtime debug logging enabled from dashboard.'
        );

        return true;
    }

    /**
     * Disable runtime debug.
     */
    public static function disable_runtime_debug() {

        delete_option( self::OPTION_KEY );

        WPSR_Logger::add(
            'runtime_debug_disabled',
            'Runtime debug logging disabled from dashboard.'
        );

        return true;
    }

    /**
     * Check runtime debug status.
     */
    public static function is_runtime_debug_enabled() {
        return (bool) get_option( self::OPTION_KEY, false );
    }

    /**
     * Apply runtime debug settings.
     *
     * Note:
     * Do not modify PHP runtime configuration (ini_set) globally.
     * This complies with the WordPress Plugin Review Team guidelines.
     */
    public static function apply_runtime_debug() {

        if ( ! self::is_runtime_debug_enabled() ) {
            return;
        }

        if ( class_exists( 'WPSR_Error_Viewer' ) ) {
            WPSR_Error_Viewer::create_log_file();
        }

        // Only increase PHP error reporting level.
        error_reporting( E_ALL );

        /*
         * Intentionally NOT using:
         *
         * ini_set( 'log_errors', ... );
         * ini_set( 'display_errors', ... );
         * ini_set( 'error_log', ... );
         *
         * WordPress.org Plugin Review Team discourages changing
         * PHP runtime configuration globally on every request.
         */
    }
}