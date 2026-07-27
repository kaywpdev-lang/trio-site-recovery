<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_REST_API {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('wpsr/v1', '/status', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'status'),
            'permission_callback' => array($this, 'permissions'),
        ));

        register_rest_route('wpsr/v1', '/restore', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'restore'),
            'permission_callback' => array($this, 'permissions'),
        ));
    }

    public function permissions() {
        return current_user_can('manage_options');
    }

    public function status() {
        return rest_ensure_response(WPSR_Site_Health::get_status());
    }

    public function restore() {
        $restored = WPSR_Snapshots::restore_latest();

        return rest_ensure_response(array(
            'success' => (bool) $restored,
            'message' => $restored ? 'Snapshot restored.' : 'No snapshot found.',
        ));
    }
}
