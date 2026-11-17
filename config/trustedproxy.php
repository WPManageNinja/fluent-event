<?php defined('ABSPATH') || exit;

/**
 * Trusted Proxies Configuration
 *
 * List IP addresses or CIDR ranges of trusted reverse proxies.
 * Forwarded headers (X-Forwarded-For, CF-Connecting-IP, X-Real-IP) are only
 * trusted when the incoming REMOTE_ADDR matches one of these entries.
 *
 * Examples:
 *   '10.0.0.1'        — single IP
 *   '10.0.0.0/8'      — CIDR range
 *
 * Cloudflare IP ranges (keep updated from https://www.cloudflare.com/ips/):
 *   IPv4: https://www.cloudflare.com/ips-v4
 *   IPv6: https://www.cloudflare.com/ips-v6
 */

return [
    'proxies' => [
        // Local / private ranges (uncomment if behind an internal load balancer)
        // '127.0.0.1',
        // '10.0.0.0/8',
        // '172.16.0.0/12',
        // '192.168.0.0/16',

        // Cloudflare (IPv4) — uncomment if using Cloudflare
        // '173.245.48.0/20',
        // '103.21.244.0/22',
        // '103.22.200.0/22',
        // '103.31.4.0/22',
        // '141.101.64.0/18',
        // '108.162.192.0/18',
        // '190.93.240.0/20',
        // '188.114.96.0/20',
        // '197.234.240.0/22',
        // '198.41.128.0/17',
        // '162.158.0.0/15',
        // '104.16.0.0/13',
        // '104.24.0.0/14',
        // '172.64.0.0/13',
        // '131.0.72.0/22',
    ],
];
