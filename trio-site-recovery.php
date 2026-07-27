<?php
/**
 * Plugin Name: Trio Site Recovery
 * Description: Recover a broken WordPress frontend from wp-admin.
 * Version: 1.0.1
 * Author: triosis
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPSR_VERSION', '1.0.0');
define('WPSR_FILE', __FILE__);
define('WPSR_PATH', plugin_dir_path(__FILE__));
define('WPSR_URL', plugin_dir_url(__FILE__));

require_once WPSR_PATH . 'includes/class-wpsr-db.php';
require_once WPSR_PATH . 'includes/class-wpsr-logger.php';
require_once WPSR_PATH . 'includes/class-wpsr-snapshots.php';
require_once WPSR_PATH . 'includes/class-wpsr-safe-mode.php';
require_once WPSR_PATH . 'includes/class-wpsr-theme-recovery.php';
require_once WPSR_PATH . 'includes/class-wpsr-plugin-manager.php';
require_once WPSR_PATH . 'includes/class-wpsr-site-health.php';
require_once WPSR_PATH . 'includes/class-wpsr-security.php';
require_once WPSR_PATH . 'includes/class-wpsr-rest-api.php';
require_once WPSR_PATH . 'includes/class-wpsr-emergency-recovery.php';
require_once WPSR_PATH . 'includes/class-wpsr-debug-manager.php';
require_once WPSR_PATH . 'includes/class-wpsr-error-viewer.php';
require_once WPSR_PATH . 'includes/class-wpsr-error-analyzer.php';
require_once WPSR_PATH . 'includes/class-wpsr-recovery-advisor.php';
require_once WPSR_PATH . 'includes/class-wpsr-frontend-health.php';
require_once WPSR_PATH . 'includes/class-wpsr-loader.php';

register_activation_hook(__FILE__, array('WPSR_DB', 'activate'));

add_action('plugins_loaded', function () {
    WPSR_Debug_Manager::apply_runtime_debug();

    $loader = new WPSR_Loader();
    $loader->init();
});
