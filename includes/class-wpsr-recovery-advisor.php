<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Recovery_Advisor {

    public static function generate($error_analysis, $frontend_health = array()) {
        $status = isset($frontend_health['status']) ? $frontend_health['status'] : 'unknown';
        $status = isset($frontend_health['status']) ? $frontend_health['status'] : 'unknown';

        if ('healthy' === $status) {
            return array(
                'severity'      => 'Low',
                'confidence'    => 95,
                'action_type'   => 'none',
                'action_target' => '',
                'title'         => 'No Critical Issue Detected',
                'summary'       => 'The frontend appears reachable. Older debug log errors may already be resolved.',
                'steps'         => array(
                    'Create a fresh snapshot.',
                    'Clear old debug logs after confirming recovery.',
                    'Keep runtime debug disabled unless troubleshooting.',
                ),
            );
        }
        if (!empty($error_analysis['found'])) {
            return self::from_error_analysis($error_analysis);
        }

        if ('error' === $status) {
            return array(
                'severity'      => 'High',
                'confidence'    => 80,
                'action_type'   => 'safe_mode',
                'action_target' => '',
                'title'         => 'Frontend Error Detected',
                'summary'       => 'The frontend appears to be broken, but no fatal error was found in the debug log.',
                'steps'         => array(
                    'Enable runtime debug logging.',
                    'Refresh the frontend page.',
                    'Check the Error Log Viewer again.',
                    'If the issue remains unclear, enable Safe Mode.',
                ),
            );
        }

        if ('warning' === $status) {
            return array(
                'severity'      => 'Medium',
                'confidence'    => 65,
                'action_type'   => 'none',
                'action_target' => '',
                'title'         => 'Frontend Warning Detected',
                'summary'       => 'The frontend is reachable, but it returned a warning-level response.',
                'steps'         => array(
                    'Review the HTTP status code.',
                    'Check recent plugin or theme changes.',
                    'Enable runtime debug logging if needed.',
                    'Create a snapshot before making changes.',
                ),
            );
        }

        return array(
            'severity'      => 'Low',
            'confidence'    => 95,
            'action_type'   => 'none',
            'action_target' => '',
            'title'         => 'No Critical Issue Detected',
            'summary'       => 'The frontend appears reachable and no fatal error was detected in the latest logs.',
            'steps'         => array(
                'Create a fresh snapshot.',
                'Keep runtime debug disabled unless troubleshooting.',
                'Review recovery history after any major change.',
            ),
        );
    }

    private static function from_error_analysis($error_analysis) {
        $source    = isset($error_analysis['source']) ? $error_analysis['source'] : 'Unknown';
        $component = isset($error_analysis['component']) ? $error_analysis['component'] : '-';

        if ('Plugin' === $source) {
            return array(
                'severity'      => 'High',
                'confidence'    => 95,
                'action_type'   => 'disable_plugin',
                'action_target' => $component,
                'title'         => 'Likely Plugin Conflict',
                'summary'       => 'A fatal error appears to be coming from the plugin: ' . $component . '.',
                'steps'         => array(
                    'Create a snapshot before making changes.',
                    'Disable the detected plugin: ' . $component . '.',
                    'Refresh the frontend and check if the site recovers.',
                    'If the issue remains, enable Safe Mode.',
                ),
            );
        }

        if ('Theme' === $source) {
            return array(
                'severity'      => 'High',
                'confidence'    => 90,
                'action_type'   => 'switch_theme',
                'action_target' => $component,
                'title'         => 'Likely Theme Issue',
                'summary'       => 'A fatal error appears to be coming from the active theme: ' . $component . '.',
                'steps'         => array(
                    'Create a snapshot before making changes.',
                    'Switch to an available default WordPress theme.',
                    'Refresh the frontend and verify recovery.',
                    'If needed, restore the latest snapshot.',
                ),
            );
        }

        if ('WordPress Core' === $source) {
            return array(
                'severity'      => 'Medium',
                'confidence'    => 70,
                'action_type'   => 'safe_mode',
                'action_target' => '',
                'title'         => 'Possible WordPress Core Issue',
                'summary'       => 'The error appears to come from WordPress core files.',
                'steps'         => array(
                    'Check recent WordPress updates.',
                    'Create a snapshot before making changes.',
                    'Disable plugins using Safe Mode to rule out conflicts.',
                    'Restore the latest known working snapshot if available.',
                ),
            );
        }

        return array(
            'severity'      => 'Medium',
            'confidence'    => 55,
            'action_type'   => 'safe_mode',
            'action_target' => '',
            'title'         => 'Unknown Error Source',
            'summary'       => 'A fatal error was detected, but the plugin could not identify its source clearly.',
            'steps'         => array(
                'Review the latest error log entry.',
                'Enable Safe Mode.',
                'Switch to a default WordPress theme if the issue continues.',
                'Run Emergency Recovery as a last resort.',
            ),
        );
    }
}