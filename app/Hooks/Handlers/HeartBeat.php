<?php

namespace FluentEvents\App\Hooks\Handlers;

use FluentEvents\App\App;

class HeartBeat
{
    /**
     * Attach a fresh REST nonce to the WordPress Heartbeat response so
     * long-lived admin pages can silently refresh authentication without
     * reloading. Keyed under the plugin slug to avoid clobbering other
     * plugins' heartbeat payloads.
     *
     * Wired via the `heartbeat_send` filter in app/Hooks/includes.php.
     *
     * @param  array  $response  Accumulated heartbeat payload from earlier filters.
     * @param  array  $data      Request data sent by the client (unused here).
     * @return array
     */
    public function handle($response, $data)
    {
        $key = App::config()->get('app.slug');

        $response[$key] = wp_create_nonce('wp_rest');

        return $response;
    }
}
