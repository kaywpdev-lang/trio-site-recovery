<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPSR_Frontend_Health {

    public static function check() {
        $url = home_url('/');

        $start_time = microtime(true);

        $response = wp_remote_get($url, array(
            'timeout'     => 10,
            'redirection' => 5,
            'sslverify'   => true,
        ));

        $response_time = round(microtime(true) - $start_time, 2);

        if (is_wp_error($response)) {
            return array(
                'status'        => 'error',
                'code'          => 'N/A',
                'message'       => $response->get_error_message(),
                'response_time' => $response_time . 's',
                'url'           => $url,
                'title'         => '-',
                'response_size' => '0 B',
                'analysis'      => 'Frontend request failed.',
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $title = '-';

        if (preg_match('/<title>(.*?)<\/title>/is', $body, $matches)) {
            $title = wp_strip_all_tags($matches[1]);
        }

        $response_size = strlen($body);
        $analysis      = 'Frontend is reachable.';
        $status        = 'healthy';
        $message       = 'Frontend is reachable.';

        if ($code >= 400 && $code < 500) {
            $status  = 'warning';
            $message = 'Frontend returned a client error.';
            $analysis = 'Client-side HTTP error detected.';
        }

        if ($code >= 500) {
            $status  = 'error';
            $message = 'Frontend may be broken or returning a server error.';
            $analysis = 'Server-side HTTP error detected.';
        }

        if ('' === trim(wp_strip_all_tags($body))) {
            $status   = 'error';
            $message  = 'Blank frontend response detected.';
            $analysis = 'Blank page detected. Possible White Screen of Death.';
        } elseif (stripos($body, 'There has been a critical error') !== false) {
            $status   = 'error';
            $message  = 'WordPress critical error detected.';
            $analysis = 'WordPress Critical Error screen detected.';
        } elseif (stripos($body, 'Fatal error') !== false) {
            $status   = 'error';
            $message  = 'PHP fatal error detected.';
            $analysis = 'PHP Fatal Error text detected in frontend response.';
        } elseif (stripos($body, 'Briefly unavailable for scheduled maintenance') !== false) {
            $status   = 'warning';
            $message  = 'Maintenance mode detected.';
            $analysis = 'WordPress Maintenance Mode detected.';
        }

        return array(
            'status'        => $status,
            'code'          => $code,
            'message'       => $message,
            'response_time' => $response_time . 's',
            'url'           => $url,
            'title'         => $title,
            'response_size' => size_format($response_size),
            'analysis'      => $analysis,
        );
    }
}