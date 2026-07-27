<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Theme_Recovery {

    public static function get_available_themes() {
        return wp_get_themes();
    }

    public static function switch_theme($theme_slug) {
        $theme_slug = sanitize_key($theme_slug);
        $theme      = wp_get_theme($theme_slug);

        if (!$theme->exists()) {
            return false;
        }

        switch_theme($theme_slug);

        WPSR_Logger::add('theme_switched', array('theme' => $theme_slug));

        return true;
    }
}
