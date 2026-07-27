<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Loader {

    public function init() {
        require_once WPSR_PATH . 'admin/class-wpsr-admin-menu.php';

        new WPSR_Admin_Menu();
        new WPSR_REST_API();
    }
}
