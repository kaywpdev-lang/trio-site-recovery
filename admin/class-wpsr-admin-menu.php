<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Admin_Menu {

    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'handle_post_actions'));
        add_action('wp_ajax_wpsr_save_deactivation_feedback', array($this, 'save_deactivation_feedback'));
    }
    public function register_menu() {
        add_menu_page(
            __('WP Site Recovery', 'trio-site-recovery'),
            __('Site Recovery', 'trio-site-recovery'),
            'manage_options',
            'trio-site-recovery',
            array($this, 'render_dashboard'),
            'dashicons-sos',
            80
        );
    }

    public function enqueue_assets($hook) {
        if ('toplevel_page_trio-site-recovery' === $hook) {
            wp_enqueue_style(
                'wpsr-admin',
                WPSR_URL . 'admin/css/wpsr-admin.css',
                array(),
                WPSR_VERSION
            );
        }

        if ('plugins.php' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'wpsr-deactivation-feedback',
            WPSR_URL . 'admin/css/wpsr-admin.css',
            array(),
            WPSR_VERSION
        );

        wp_enqueue_script(
            'wpsr-deactivation-feedback',
            WPSR_URL . 'admin/js/wpsr-deactivation-feedback.js',
            array(),
            WPSR_VERSION,
            true
        );

        wp_localize_script(
            'wpsr-deactivation-feedback',
            'wpsrFeedback',
            array(
                'pluginFile' => plugin_basename(WPSR_FILE),
                'ajaxUrl'    => admin_url('admin-ajax.php'),
                'nonce'      => wp_create_nonce('wpsr_deactivation_feedback_nonce'),
            )
        );
    }

    public function save_deactivation_feedback() {
        check_ajax_referer('wpsr_deactivation_feedback_nonce', 'nonce');

        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'trio-site-recovery')), 403);
        }

        $reason = isset($_POST['reason']) ? sanitize_key(wp_unslash($_POST['reason'])) : '';
        $details = isset($_POST['details']) ? sanitize_textarea_field(wp_unslash($_POST['details'])) : '';

        $allowed_reasons = array(
            'setup_difficult',
            'not_working',
            'missing_feature',
            'found_alternative',
            'temporary',
            'other',
        );

        if (!in_array($reason, $allowed_reasons, true)) {
            wp_send_json_error(array('message' => __('Please select a valid reason.', 'trio-site-recovery')), 400);
        }

        $feedback = get_option('wpsr_deactivation_feedback', array());
        if (!is_array($feedback)) {
            $feedback = array();
        }

        $feedback[] = array(
            'reason'     => $reason,
            'details'    => $details,
            'version'    => WPSR_VERSION,
            'created_at' => current_time('mysql'),
        );

        update_option('wpsr_deactivation_feedback', array_slice($feedback, -50), false);
        $reason_labels = array(
    'setup_difficult'  => 'Setup was difficult',
    'not_working'      => 'Plugin did not work',
    'missing_feature'  => 'A feature is missing',
    'found_alternative'=> 'Found another plugin',
    'temporary'        => 'Temporary deactivation',
    'other'            => 'Other',
);

$reason_label = isset($reason_labels[$reason])
    ? $reason_labels[$reason]
    : $reason;

$owner_email = 'kay.wpdev@gmail.com';

$subject = sprintf(
    '[Trio Site Recovery] Deactivation: %s',
    $reason_label
);

$message = sprintf(
    "A user has deactivated Trio Site Recovery.\n\n" .
    "Reason: %s\n" .
    "Additional Details: %s\n" .
    "Plugin Version: %s\n" .
    "WordPress Version: %s\n" .
    "Website: %s\n" .
    "Submitted At: %s\n",
    $reason_label,
    !empty($details) ? $details : 'No additional details provided.',
    WPSR_VERSION,
    get_bloginfo('version'),
    home_url(),
    current_time('mysql')
);

$headers = array(
    'Content-Type: text/plain; charset=UTF-8',
);

$mail_sent = wp_mail(
    $owner_email,
    $subject,
    $message,
    $headers
);
        wp_send_json_success(array('message' => __('Feedback saved.', 'trio-site-recovery')));
    }

    private function resolve_plugin_file($action_target) {
        if (empty($action_target)) {
            return '';
        }

        $active_plugins = WPSR_Plugin_Manager::get_active_plugins();

        if (in_array($action_target, $active_plugins, true)) {
            return $action_target;
        }

        foreach ($active_plugins as $active_plugin) {
            if (0 === strpos($active_plugin, $action_target . '/')) {
                return $active_plugin;
            }
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            if ($plugin_file === $action_target || 0 === strpos($plugin_file, $action_target . '/')) {
                return $plugin_file;
            }
        }

        return $action_target;
    }

    private function redirect_with_notice($notice) {
        wp_safe_redirect(
            add_query_arg(
                'wpsr_notice',
                sanitize_key($notice),
                menu_page_url('trio-site-recovery', false)
            )
        );
        exit;
    }

    public function handle_post_actions() {
        if (!WPSR_Security::can_manage()) {
            return;
        }

        if (isset($_POST['wpsr_run_recommended_fix'])) {
            check_admin_referer('wpsr_recommended_fix_action', 'wpsr_recommended_fix_nonce');

            $action_type   = isset($_POST['wpsr_action_type']) ? sanitize_text_field(wp_unslash($_POST['wpsr_action_type'])) : '';
            $action_target = isset($_POST['wpsr_action_target']) ? sanitize_text_field(wp_unslash($_POST['wpsr_action_target'])) : '';
            $notice        = 'recommended_fix_applied';

            if ('safe_mode' === $action_type) {
                WPSR_Safe_Mode::enable();
            } elseif ('disable_plugin' === $action_type && !empty($action_target)) {
                WPSR_Snapshots::create();
                $plugin_file = $this->resolve_plugin_file($action_target);

                if (!WPSR_Plugin_Manager::disable_plugin($plugin_file)) {
                    $notice = 'recommended_fix_failed';
                }
            } elseif ('switch_theme' === $action_type) {
                WPSR_Snapshots::create();

                $fallback_themes = array(
                    'twentytwentyfive',
                    'twentytwentyfour',
                    'twentytwentythree',
                );

                $switched = false;

                foreach ($fallback_themes as $theme_slug) {
                    if (wp_get_theme($theme_slug)->exists()) {
                        $switched = WPSR_Theme_Recovery::switch_theme($theme_slug);
                        break;
                    }
                }

                if (!$switched) {
                    $notice = 'recommended_fix_failed';
                }
            } else {
                $notice = 'recommended_fix_failed';
            }

            $this->redirect_with_notice($notice);
        }

        if (isset($_POST['wpsr_emergency_recovery'])) {
            check_admin_referer('wpsr_emergency_recovery_action', 'wpsr_emergency_recovery_nonce');

            $recovery = new WPSR_Emergency_Recovery();
            $recovery->recover();

            $this->redirect_with_notice('emergency_recovery_completed');
        }

        if (isset($_POST['wpsr_create_snapshot'])) {
            check_admin_referer('wpsr_create_snapshot_action', 'wpsr_nonce');

            WPSR_Snapshots::create();

            $this->redirect_with_notice('snapshot_created');
        }

        if (isset($_POST['wpsr_restore_snapshot'])) {
            check_admin_referer('wpsr_restore_snapshot_action', 'wpsr_restore_nonce');

            $restored = WPSR_Snapshots::restore_latest();

            $this->redirect_with_notice($restored ? 'snapshot_restored' : 'snapshot_not_found');
        }

        if (isset($_POST['wpsr_enable_safe_mode'])) {
            check_admin_referer('wpsr_safe_mode_action', 'wpsr_safe_mode_nonce');

            WPSR_Safe_Mode::enable();

            $this->redirect_with_notice('safe_mode_enabled');
        }

        if (isset($_POST['wpsr_switch_theme'])) {
            check_admin_referer('wpsr_switch_theme_action', 'wpsr_theme_nonce');

            $theme   = isset($_POST['wpsr_theme']) ? WPSR_Security::clean_text(wp_unslash($_POST['wpsr_theme'])) : '';
            $success = WPSR_Theme_Recovery::switch_theme($theme);

            $this->redirect_with_notice($success ? 'theme_switched' : 'theme_switch_failed');
        }

        if (isset($_POST['wpsr_disable_plugin'])) {
            check_admin_referer('wpsr_disable_plugin_action', 'wpsr_plugin_nonce');

            $plugin_file = isset($_POST['wpsr_plugin']) ? WPSR_Security::clean_text(wp_unslash($_POST['wpsr_plugin'])) : '';
            $success     = WPSR_Plugin_Manager::disable_plugin($plugin_file);

            $this->redirect_with_notice($success ? 'plugin_disabled' : 'plugin_disable_failed');
        }

        if (isset($_POST['wpsr_enable_runtime_debug'])) {
            check_admin_referer('wpsr_enable_runtime_debug_action', 'wpsr_runtime_debug_nonce');

            WPSR_Debug_Manager::enable_runtime_debug();

            $this->redirect_with_notice('runtime_debug_enabled');
        }

        if (isset($_POST['wpsr_disable_runtime_debug'])) {
            check_admin_referer('wpsr_disable_runtime_debug_action', 'wpsr_runtime_debug_nonce');

            WPSR_Debug_Manager::disable_runtime_debug();

            $this->redirect_with_notice('runtime_debug_disabled');
        }

        if (isset($_POST['wpsr_clear_error_log'])) {
            check_admin_referer('wpsr_clear_error_log_action', 'wpsr_clear_error_log_nonce');

            $success = WPSR_Error_Viewer::clear_log();

            $this->redirect_with_notice($success ? 'error_log_cleared' : 'error_log_clear_failed');
        }
    }

    private function handle_actions() {
        if (!isset($_GET['wpsr_notice'])) {
            return;
        }

        $notice = sanitize_key(wp_unslash($_GET['wpsr_notice']));

        $notices = array(
            'recommended_fix_applied'       => array('success', __('Recommended recovery action applied successfully.', 'trio-site-recovery')),
            'recommended_fix_failed'        => array('error', __('Recommended recovery action failed. Please use the manual recovery tools below.', 'trio-site-recovery')),
            'emergency_recovery_completed'  => array('success', __('Emergency Recovery completed successfully.', 'trio-site-recovery')),
            'snapshot_created'              => array('success', __('Snapshot created successfully.', 'trio-site-recovery')),
            'snapshot_restored'             => array('success', __('Snapshot restored successfully.', 'trio-site-recovery')),
            'snapshot_not_found'            => array('error', __('No snapshot found.', 'trio-site-recovery')),
            'safe_mode_enabled'             => array('warning', __('Safe Mode enabled. All plugins disabled except WP Site Recovery.', 'trio-site-recovery')),
            'theme_switched'                => array('success', __('Theme switched successfully.', 'trio-site-recovery')),
            'theme_switch_failed'           => array('error', __('Theme switch failed.', 'trio-site-recovery')),
            'plugin_disabled'               => array('success', __('Plugin disabled successfully.', 'trio-site-recovery')),
            'plugin_disable_failed'         => array('error', __('Plugin disable failed.', 'trio-site-recovery')),
            'runtime_debug_enabled'         => array('success', __('Runtime debug logging enabled.', 'trio-site-recovery')),
            'runtime_debug_disabled'        => array('success', __('Runtime debug logging disabled.', 'trio-site-recovery')),
            'error_log_cleared'             => array('success', __('Error log cleared successfully.', 'trio-site-recovery')),
            'error_log_clear_failed'        => array('error', __('Error log could not be cleared.', 'trio-site-recovery')),
        );

        if (!isset($notices[$notice])) {
            return;
        }

        $type    = $notices[$notice][0];
        $message = $notices[$notice][1];

        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private function render_key_value_table($items) {
        echo '<table class="widefat striped wpsr-table"><tbody>';

        foreach ($items as $key => $value) {
            echo '<tr>';
            echo '<td><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</strong></td>';
            echo '<td>' . esc_html(is_bool($value) ? ($value ? __('Enabled', 'trio-site-recovery') : __('Disabled', 'trio-site-recovery')) : $value) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    public function render_dashboard() {
        if (!WPSR_Security::can_manage()) {
            WPSR_Security::deny_access();
        }

        $this->handle_actions();

        $status           = WPSR_Site_Health::get_status();
        $debug_status     = WPSR_Debug_Manager::get_status();
        $frontend_health  = WPSR_Frontend_Health::check();
        $error_analysis   = WPSR_Error_Analyzer::analyze();
        $recovery_advisor = WPSR_Recovery_Advisor::generate($error_analysis, $frontend_health);
        $snapshots        = WPSR_Snapshots::get_all();
        $logs             = WPSR_Logger::get_logs();
        $themes           = WPSR_Theme_Recovery::get_available_themes();
        $active_plugins   = WPSR_Plugin_Manager::get_active_plugins();

        $current_plugin_file = defined('WPSR_FILE') ? plugin_basename(WPSR_FILE) : '';
        $recoverable_plugins = array();

        if (is_array($active_plugins)) {
            foreach ($active_plugins as $active_plugin) {
                if ($active_plugin !== $current_plugin_file) {
                    $recoverable_plugins[] = $active_plugin;
                }
            }
        }

        $snapshots_count = is_array($snapshots) ? count($snapshots) : 0;
        $logs_count      = is_array($logs) ? count($logs) : 0;
        $plugins_count   = isset($status['plugins_count']) ? $status['plugins_count'] : 0;
        $runtime_debug   = WPSR_Debug_Manager::is_runtime_debug_enabled();

        echo '<div class="wrap wpsr-wrap">';
        echo '<div class="wpsr-header">';
        echo '<div>';
        echo '<h1>' . esc_html__('WP Site Recovery', 'trio-site-recovery') . '</h1>';
        echo '<p>' . esc_html__('Recover common WordPress frontend issues directly from wp-admin.', 'trio-site-recovery') . '</p>';
        echo '</div>';
        echo '<span class="wpsr-badge">' . esc_html__('Recovery Toolkit', 'trio-site-recovery') . '</span>';
        echo '</div>';

        echo '<div class="wpsr-summary-grid">';
        echo '<div class="wpsr-summary-card"><span>' . esc_html__('Active Theme', 'trio-site-recovery') . '</span><strong>' . esc_html(isset($status['theme']) ? $status['theme'] : '-') . '</strong></div>';
        echo '<div class="wpsr-summary-card"><span>' . esc_html__('Plugins', 'trio-site-recovery') . '</span><strong>' . esc_html($plugins_count) . '</strong></div>';
        echo '<div class="wpsr-summary-card"><span>' . esc_html__('Snapshots', 'trio-site-recovery') . '</span><strong>' . esc_html($snapshots_count) . '</strong></div>';
        echo '<div class="wpsr-summary-card"><span>' . esc_html__('Runtime Debug', 'trio-site-recovery') . '</span><strong>' . esc_html($runtime_debug ? __('Enabled', 'trio-site-recovery') : __('Disabled', 'trio-site-recovery')) . '</strong></div>';
        echo '</div>';

        echo '<div class="wpsr-grid">';

        $frontend_status      = $frontend_health['status'];
        $frontend_badge_class = 'wpsr-status-success';

        if ('warning' === $frontend_status) {
            $frontend_badge_class = 'wpsr-status-warning';
        }

        if ('error' === $frontend_status) {
            $frontend_badge_class = 'wpsr-status-danger';
        }

        echo '<div class="wpsr-card">';
        echo '<div class="wpsr-card-head">';
        echo '<h2>' . esc_html__('Frontend Health Check', 'trio-site-recovery') . '</h2>';
        echo '<span class="' . esc_attr($frontend_badge_class) . '">' . esc_html(ucfirst($frontend_status)) . '</span>';
        echo '</div>';

        echo '<table class="widefat striped wpsr-table"><tbody>';
        echo '<tr><td><strong>' . esc_html__('URL', 'trio-site-recovery') . '</strong></td><td>' . esc_html($frontend_health['url']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Status', 'trio-site-recovery') . '</strong></td><td>' . esc_html($frontend_health['status']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('HTTP Code', 'trio-site-recovery') . '</strong></td><td>' . esc_html($frontend_health['code']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Response Time', 'trio-site-recovery') . '</strong></td><td>' . esc_html($frontend_health['response_time']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Page Title', 'trio-site-recovery') . '</strong></td><td>' . esc_html($frontend_health['title']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Response Size', 'trio-site-recovery') . '</strong></td><td>' . esc_html($frontend_health['response_size']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Message', 'trio-site-recovery') . '</strong></td><td>' . esc_html($frontend_health['message']) . '</td></tr>';
        echo '</tbody></table>';

        echo '<div class="notice notice-info inline wpsr-analysis-box">';
        echo '<p><strong>' . esc_html__('Analysis:', 'trio-site-recovery') . '</strong> ' . esc_html($frontend_health['analysis']) . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<div class="wpsr-card wpsr-card-danger">';
        echo '<div class="wpsr-card-head"><h2>' . esc_html__('Emergency Recovery', 'trio-site-recovery') . '</h2><span>' . esc_html__('High impact', 'trio-site-recovery') . '</span></div>';
        echo '<p>' . esc_html__('Creates a snapshot, disables plugins, and switches to a fallback default theme.', 'trio-site-recovery') . '</p>';
        echo '<form method="post">';
        wp_nonce_field('wpsr_emergency_recovery_action', 'wpsr_emergency_recovery_nonce');
        echo '<button type="submit" name="wpsr_emergency_recovery" class="button button-primary wpsr-button-danger" onclick="return confirm(\'This will disable all plugins and switch to a default theme. Continue?\');">' . esc_html__('Run Emergency Recovery', 'trio-site-recovery') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="wpsr-card">';
        echo '<div class="wpsr-card-head"><h2>' . esc_html__('Snapshot Actions', 'trio-site-recovery') . '</h2><span>' . esc_html__('Restore point', 'trio-site-recovery') . '</span></div>';
        echo '<p>' . esc_html__('Create or restore the latest saved plugin/theme state.', 'trio-site-recovery') . '</p>';
        echo '<div class="wpsr-action-row">';
        echo '<form method="post">';
        wp_nonce_field('wpsr_create_snapshot_action', 'wpsr_nonce');
        echo '<button type="submit" name="wpsr_create_snapshot" class="button button-primary">' . esc_html__('Create Snapshot', 'trio-site-recovery') . '</button>';
        echo '</form>';
        echo '<form method="post">';
        wp_nonce_field('wpsr_restore_snapshot_action', 'wpsr_restore_nonce');
        echo '<button type="submit" name="wpsr_restore_snapshot" class="button">' . esc_html__('Restore Latest Snapshot', 'trio-site-recovery') . '</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';

        echo '<div class="wpsr-card">';
        echo '<div class="wpsr-card-head"><h2>' . esc_html__('Site Health Dashboard', 'trio-site-recovery') . '</h2><span>' . esc_html__('System info', 'trio-site-recovery') . '</span></div>';
        $this->render_key_value_table($status);
        echo '</div>';

        echo '<div class="wpsr-card">';
        echo '<div class="wpsr-card-head"><h2>' . esc_html__('Debug Manager', 'trio-site-recovery') . '</h2><span>' . esc_html__('Runtime only', 'trio-site-recovery') . '</span></div>';
        $this->render_key_value_table($debug_status);

        echo '<form method="post" class="wpsr-form-space">';
        if ($runtime_debug) {
            wp_nonce_field('wpsr_disable_runtime_debug_action', 'wpsr_runtime_debug_nonce');
            echo '<button type="submit" name="wpsr_disable_runtime_debug" class="button">' . esc_html__('Disable Runtime Debug Logging', 'trio-site-recovery') . '</button>';
        } else {
            wp_nonce_field('wpsr_enable_runtime_debug_action', 'wpsr_runtime_debug_nonce');
            echo '<button type="submit" name="wpsr_enable_runtime_debug" class="button button-primary">' . esc_html__('Enable Runtime Debug Logging', 'trio-site-recovery') . '</button>';
        }
        echo '</form>';
        echo '</div>';

        echo '<div class="wpsr-card">';
        echo '<div class="wpsr-card-head"><h2>' . esc_html__('Safe Mode', 'trio-site-recovery') . '</h2><span>' . esc_html__('Plugin isolation', 'trio-site-recovery') . '</span></div>';
        echo '<p>' . esc_html__('Disable all active plugins after creating a snapshot.', 'trio-site-recovery') . '</p>';
        echo '<form method="post">';
        wp_nonce_field('wpsr_safe_mode_action', 'wpsr_safe_mode_nonce');
        echo '<button type="submit" name="wpsr_enable_safe_mode" class="button button-secondary">' . esc_html__('Enable Safe Mode', 'trio-site-recovery') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="wpsr-card">';
        echo '<div class="wpsr-card-head"><h2>' . esc_html__('Theme Recovery', 'trio-site-recovery') . '</h2><span>' . esc_html__('Theme switch', 'trio-site-recovery') . '</span></div>';
        echo '<form method="post" class="wpsr-inline-form">';
        wp_nonce_field('wpsr_switch_theme_action', 'wpsr_theme_nonce');
        echo '<select name="wpsr_theme">';

        foreach ($themes as $slug => $theme) {
            echo '<option value="' . esc_attr($slug) . '">' . esc_html($theme->get('Name')) . '</option>';
        }

        echo '</select>';
        echo '<button type="submit" name="wpsr_switch_theme" class="button button-primary">' . esc_html__('Switch Theme', 'trio-site-recovery') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="wpsr-card">';
        echo '<div class="wpsr-card-head"><h2>' . esc_html__('Quick Plugin Disable', 'trio-site-recovery') . '</h2><span>' . esc_html__('Targeted fix', 'trio-site-recovery') . '</span></div>';

        if (!empty($recoverable_plugins)) {
            echo '<form method="post" class="wpsr-inline-form">';
            wp_nonce_field('wpsr_disable_plugin_action', 'wpsr_plugin_nonce');
            echo '<select name="wpsr_plugin">';

            foreach ($recoverable_plugins as $plugin_file) {
                echo '<option value="' . esc_attr($plugin_file) . '">' . esc_html($plugin_file) . '</option>';
            }

            echo '</select>';
            echo '<button type="submit" name="wpsr_disable_plugin" class="button button-primary">' . esc_html__('Disable Plugin', 'trio-site-recovery') . '</button>';
            echo '</form>';
        } else {
            echo '<p>' . esc_html__('No active plugins found.', 'trio-site-recovery') . '</p>';
        }

        echo '</div>';

        echo '<div class="wpsr-card wpsr-card-wide">';
        echo '<div class="wpsr-card-head">';
        echo '<h2>' . esc_html__('Recovery Advisor', 'trio-site-recovery') . '</h2>';
        echo '<span>' . esc_html($recovery_advisor['severity']) . ' / ' . esc_html($recovery_advisor['confidence']) . '%</span>';
        echo '</div>';

        echo '<p><strong>' . esc_html($recovery_advisor['title']) . '</strong></p>';
        echo '<p>' . esc_html($recovery_advisor['summary']) . '</p>';

        if (!empty($recovery_advisor['steps'])) {
            echo '<ol class="wpsr-advisor-steps">';

            foreach ($recovery_advisor['steps'] as $step) {
                echo '<li>' . esc_html($step) . '</li>';
            }

            echo '</ol>';
        }

        if (!empty($recovery_advisor['action_type']) && 'none' !== $recovery_advisor['action_type']) {
            echo '<form method="post" class="wpsr-form-space">';
            wp_nonce_field('wpsr_recommended_fix_action', 'wpsr_recommended_fix_nonce');
            echo '<input type="hidden" name="wpsr_action_type" value="' . esc_attr($recovery_advisor['action_type']) . '">';
            echo '<input type="hidden" name="wpsr_action_target" value="' . esc_attr($recovery_advisor['action_target']) . '">';
            echo '<button type="submit" name="wpsr_run_recommended_fix" class="button button-primary" onclick="return confirm(\'Apply recommended recovery fix?\');">' . esc_html__('Apply Recommended Fix', 'trio-site-recovery') . '</button>';
            echo '</form>';
        }

        echo '</div>';

        echo '<div class="wpsr-card wpsr-card-wide">';
        echo '<div class="wpsr-card-head">';
        echo '<h2>' . esc_html__('Fatal Error Analyzer', 'trio-site-recovery') . '</h2>';
        echo '<span>' . esc_html($error_analysis['found'] ? 'Detected' : 'No fatal error') . '</span>';
        echo '</div>';

        echo '<table class="widefat striped wpsr-table"><tbody>';
        echo '<tr><td><strong>' . esc_html__('Error Type', 'trio-site-recovery') . '</strong></td><td>' . esc_html($error_analysis['type']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Source', 'trio-site-recovery') . '</strong></td><td>' . esc_html($error_analysis['source']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Component', 'trio-site-recovery') . '</strong></td><td>' . esc_html($error_analysis['component']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('File', 'trio-site-recovery') . '</strong></td><td>' . esc_html($error_analysis['file']) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Suggestion', 'trio-site-recovery') . '</strong></td><td><strong>' . esc_html($error_analysis['suggestion']) . '</strong></td></tr>';
        echo '</tbody></table>';

        if (!empty($error_analysis['message'])) {
            echo '<div class="wpsr-log-box" style="margin-top:15px;">';
            echo esc_html($error_analysis['message']);
            echo '</div>';
        }

        echo '</div>';

        echo '<div class="wpsr-card wpsr-card-wide">';
        echo '<div class="wpsr-card-head"><h2>' . esc_html__('Error Log Viewer', 'trio-site-recovery') . '</h2><span>' . esc_html__('Latest 100 lines', 'trio-site-recovery') . '</span></div>';

        if (WPSR_Error_Viewer::exists()) {
            echo '<div class="wpsr-meta-row">';
            echo '<span><strong>' . esc_html__('File Size:', 'trio-site-recovery') . '</strong> ' . esc_html(WPSR_Error_Viewer::get_file_size()) . '</span>';
            echo '<span><strong>' . esc_html__('Last Modified:', 'trio-site-recovery') . '</strong> ' . esc_html(WPSR_Error_Viewer::get_last_modified()) . '</span>';
            echo '</div>';

            echo '<form method="post" class="wpsr-form-space">';
            wp_nonce_field('wpsr_clear_error_log_action', 'wpsr_clear_error_log_nonce');
            echo '<button type="submit" name="wpsr_clear_error_log" class="button" onclick="return confirm(\'Clear debug log?\');">' . esc_html__('Clear Log', 'trio-site-recovery') . '</button>';
            echo '</form>';

            $error_lines = WPSR_Error_Viewer::get_latest_lines(100);

            if (!empty($error_lines)) {
                echo '<div class="wpsr-log-box">';

                foreach ($error_lines as $line) {
                    echo esc_html($line) . '<br>';
                }

                echo '</div>';
            } else {
                echo '<p>' . esc_html__('Debug log file exists but it is empty.', 'trio-site-recovery') . '</p>';
            }
        } else {
            echo '<p>' . esc_html__('No debug.log file found in wp-content. Please check server file permissions.', 'trio-site-recovery') . '</p>';
        }

        echo '</div>';

        echo '<div class="wpsr-card wpsr-card-wide">';
        echo '<div class="wpsr-card-head"><h2>' . esc_html__('Snapshots', 'trio-site-recovery') . '</h2><span>' . esc_html($snapshots_count) . '</span></div>';

        if (!empty($snapshots)) {
            echo '<table class="widefat striped wpsr-table">';
            echo '<thead><tr><th>' . esc_html__('ID', 'trio-site-recovery') . '</th><th>' . esc_html__('Theme', 'trio-site-recovery') . '</th><th>' . esc_html__('Plugins', 'trio-site-recovery') . '</th><th>' . esc_html__('Date', 'trio-site-recovery') . '</th></tr></thead><tbody>';

            foreach ($snapshots as $snapshot) {
                $plugins       = json_decode($snapshot->plugins, true);
                $plugins_count = is_array($plugins) ? count($plugins) : 0;

                echo '<tr>';
                echo '<td>' . esc_html($snapshot->id) . '</td>';
                echo '<td>' . esc_html($snapshot->theme) . '</td>';
                echo '<td>' . esc_html($plugins_count) . ' ' . esc_html__('plugins', 'trio-site-recovery') . '</td>';
                echo '<td>' . esc_html($snapshot->created_at) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__('No snapshots found.', 'trio-site-recovery') . '</p>';
        }

        echo '</div>';

        echo '<div class="wpsr-card wpsr-card-wide">';
        echo '<div class="wpsr-card-head"><h2>' . esc_html__('Recovery History', 'trio-site-recovery') . '</h2><span>' . esc_html($logs_count) . '</span></div>';

        if (!empty($logs)) {
            echo '<table class="widefat striped wpsr-table">';
            echo '<thead><tr><th>' . esc_html__('ID', 'trio-site-recovery') . '</th><th>' . esc_html__('User ID', 'trio-site-recovery') . '</th><th>' . esc_html__('Action', 'trio-site-recovery') . '</th><th>' . esc_html__('Details', 'trio-site-recovery') . '</th><th>' . esc_html__('Date', 'trio-site-recovery') . '</th></tr></thead><tbody>';

            foreach ($logs as $log) {
                echo '<tr>';
                echo '<td>' . esc_html($log->id) . '</td>';
                echo '<td>' . esc_html($log->user_id) . '</td>';
                echo '<td>' . esc_html($log->action) . '</td>';
                echo '<td>' . esc_html($log->details) . '</td>';
                echo '<td>' . esc_html($log->created_at) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__('No recovery history found.', 'trio-site-recovery') . '</p>';
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
