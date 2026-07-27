<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Error_Analyzer {

    public static function analyze() {
        if (!class_exists('WPSR_Error_Viewer') || !WPSR_Error_Viewer::exists()) {
            return self::empty_result('No debug log file found.');
        }

        $lines = WPSR_Error_Viewer::get_latest_lines(200);

        if (empty($lines)) {
            return self::empty_result('Debug log is empty.');
        }

        $latest_error = '';

        foreach (array_reverse($lines) as $line) {
            if (
                stripos($line, 'PHP Fatal error') !== false ||
                stripos($line, 'PHP Parse error') !== false ||
                stripos($line, 'Uncaught Error') !== false ||
                stripos($line, 'critical error') !== false
            ) {
                $latest_error = $line;
                break;
            }
        }

        if (empty($latest_error)) {
            return self::empty_result('No fatal or parse errors detected in latest log lines.');
        }

        $source = self::detect_source($latest_error);
        $type   = self::detect_type($latest_error);

        return array(
            'found'      => true,
            'type'       => $type,
            'source'     => $source['source'],
            'component'  => $source['component'],
            'file'       => $source['file'],
            'suggestion' => self::get_suggestion($source['source'], $source['component']),
            'message'    => $latest_error,
        );
    }

    private static function empty_result($message) {
        return array(
            'found'      => false,
            'type'       => '-',
            'source'     => '-',
            'component'  => '-',
            'file'       => '-',
            'suggestion' => $message,
            'message'    => $message,
        );
    }

    private static function detect_type($line) {
        if (stripos($line, 'PHP Parse error') !== false) {
            return 'PHP Parse Error';
        }

        if (stripos($line, 'PHP Fatal error') !== false || stripos($line, 'Uncaught Error') !== false) {
            return 'PHP Fatal Error';
        }

        return 'Unknown Error';
    }

    private static function detect_source($line) {
        $file = '-';

        if (preg_match('/in\s+(.+?\.php)/i', $line, $matches)) {
            $file = $matches[1];
        }

        $normalized = str_replace('\\', '/', $file);

        if (strpos($normalized, '/wp-content/plugins/') !== false) {
            $parts = explode('/wp-content/plugins/', $normalized);
            $plugin_path = isset($parts[1]) ? $parts[1] : '';
            $plugin_slug = explode('/', $plugin_path)[0];

            return array(
                'source'    => 'Plugin',
                'component' => $plugin_slug,
                'file'      => $file,
            );
        }

        if (strpos($normalized, '/wp-content/themes/') !== false) {
            $parts = explode('/wp-content/themes/', $normalized);
            $theme_path = isset($parts[1]) ? $parts[1] : '';
            $theme_slug = explode('/', $theme_path)[0];

            return array(
                'source'    => 'Theme',
                'component' => $theme_slug,
                'file'      => $file,
            );
        }

        if (strpos($normalized, '/wp-admin/') !== false || strpos($normalized, '/wp-includes/') !== false) {
            return array(
                'source'    => 'WordPress Core',
                'component' => 'core',
                'file'      => $file,
            );
        }

        return array(
            'source'    => 'Unknown',
            'component' => '-',
            'file'      => $file,
        );
    }

    private static function get_suggestion($source, $component) {
        if ('Plugin' === $source && '-' !== $component) {
            return 'Suggested action: disable the plugin "' . $component . '" and re-check the frontend.';
        }

        if ('Theme' === $source && '-' !== $component) {
            return 'Suggested action: switch away from the theme "' . $component . '" to a default WordPress theme.';
        }

        if ('WordPress Core' === $source) {
            return 'Suggested action: check recent WordPress updates or restore the latest safe snapshot.';
        }

        return 'Suggested action: review the latest debug log and try Safe Mode.';
    }
}