<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Error_Viewer {

    private static function get_filesystem() {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();

        return $wp_filesystem;
    }

    public static function get_log_file() {
        return trailingslashit(WP_CONTENT_DIR) . 'debug.log';
    }

    public static function create_log_file() {
        $file = self::get_log_file();

        if (file_exists($file)) {
            return true;
        }

        $filesystem = self::get_filesystem();

        if (!$filesystem) {
            return false;
        }

        return $filesystem->put_contents($file, '', FS_CHMOD_FILE);
    }

    public static function exists() {
        return file_exists(self::get_log_file()) || self::create_log_file();
    }

    public static function get_latest_lines($lines = 100) {
        $file = self::get_log_file();

        if (!file_exists($file) || !is_readable($file)) {
            return array();
        }

        $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (empty($content) || !is_array($content)) {
            return array();
        }

        return array_slice($content, -absint($lines));
    }

    public static function get_file_size() {
        $file = self::get_log_file();

        if (!file_exists($file)) {
            return '0 KB';
        }

        return size_format(filesize($file));
    }

    public static function get_last_modified() {
        $file = self::get_log_file();

        if (!file_exists($file)) {
            return 'N/A';
        }

        return date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            filemtime($file)
        );
    }

    public static function clear_log() {
        $file = self::get_log_file();

        if (!self::exists()) {
            return false;
        }

        $filesystem = self::get_filesystem();

        if (!$filesystem) {
            return false;
        }

        $result = $filesystem->put_contents($file, '', FS_CHMOD_FILE);

        if (!$result) {
            return false;
        }

        WPSR_Logger::add(
            'error_log_cleared',
            'Debug log file cleared from dashboard.'
        );

        return true;
    }
}