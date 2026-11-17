<?php

namespace FluentEvent\App\Utils;

use FluentEvent\Framework\Foundation\App;
use FluentEvent\Framework\Support\Arr;

class Utility
{
    /**
     * The visitor's IP address, for rate limiting.
     *
     * Forwarded headers count only from Cloudflare, found by its published ranges,
     * or from a proxy listed in config/trustedproxy.php or added with the
     * fluent_event/trusted_proxies filter. A site with another setup can set the
     * address itself through the shared fluent_auth/user_ip filter.
     *
     * @return string
     */
    public static function getIp()
    {
        if (empty($_SERVER['REMOTE_ADDR'])) {
            return '127.0.0.1';
        }

        $serverData = [];

        foreach (['REMOTE_ADDR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
            if (isset($_SERVER[$key])) {
                $serverData[$key] = sanitize_text_field(wp_unslash($_SERVER[$key]));
            }
        }

        // Filterable, so a site behind its own reverse proxy does not have to edit a plugin file.
        $trustedProxies = (array) apply_filters(
            'fluent_event/trusted_proxies',
            (array) App::config()->get('trustedproxy.proxies', [])
        );

        $ip = self::resolveClientIp($serverData, $trustedProxies);

        // One filter for the visitor IP across the Fluent plugins.
        return (string) apply_filters('fluent_auth/user_ip', $ip);
    }

    /**
     * Pick the client address out of a request's server vars.
     *
     * @param array $serverData $_SERVER or an equivalent
     * @param array $trustedProxies IPs or CIDR ranges whose X-Forwarded-For is believed
     * @return string
     */
    public static function resolveClientIp($serverData, $trustedProxies = [])
    {
        $remoteAddr = self::normalizeIp(Arr::get($serverData, 'REMOTE_ADDR'));
        $cloudflareIp = rest_is_ip_address(self::normalizeIp(Arr::get($serverData, 'HTTP_CF_CONNECTING_IP')));

        // A proxy appends the peer it saw, so only the right-most hop is reliable;
        // anything to its left is whatever the client chose to send.
        $hops = explode(',', (string) Arr::get($serverData, 'HTTP_X_FORWARDED_FOR'));
        $forwardedIp = rest_is_ip_address(self::normalizeIp(end($hops)));

        if ($cloudflareIp && self::isCfIp($remoteAddr)) {
            return $cloudflareIp;
        }

        if ($forwardedIp) {
            foreach ($trustedProxies as $proxy) {
                if (self::ipInRange($remoteAddr, $proxy)) {
                    return $forwardedIp;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * @param string $ip
     * @return bool
     */
    public static function isCfIp($ip)
    {
        if (!$ip) {
            return false;
        }

        // Cloudflare reaches IPv6 origins over IPv6, so a v4-only list puts every visitor behind one edge.
        // Filterable so a range change does not have to wait for a release. Source: https://www.cloudflare.com/ips/
        $ranges = (array) apply_filters('fluent_event/cloudflare_ip_ranges', [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        ]);

        foreach ($ranges as $range) {
            if (self::ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $ip
     * @return string The bare address: no port (1.2.3.4:80, [2001:db8::1]:80), and an
     *                IPv4-mapped IPv6 address (::ffff:1.2.3.4) as the IPv4 address it wraps.
     */
    private static function normalizeIp($ip)
    {
        $ip = trim((string) $ip);

        if (preg_match('/^\[(.+)\](?::\d+)?$/', $ip, $matches)) {
            $ip = $matches[1];
        } elseif (preg_match('/^(\d+\.\d+\.\d+\.\d+):\d+$/', $ip, $matches)) {
            $ip = $matches[1];
        }

        // A dual-stack server reports IPv4 clients this way; left mapped, they miss every IPv4 range.
        $bin = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? inet_pton($ip) : false;

        if ($bin && substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            return inet_ntop(substr($bin, 12));
        }

        return $ip;
    }

    /**
     * CIDR or exact match for both IPv4 and IPv6, on the packed binary form,
     * because ip2long() returns false for any IPv6 address.
     *
     * @param string $ip
     * @param string $range
     * @return bool
     */
    private static function ipInRange($ip, $range)
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range, 2);

        if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($subnet, FILTER_VALIDATE_IP)) {
            return false;
        }

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        // Differing lengths means v4 against v6.
        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = (int) $bits;

        if ($bits < 0 || $bits > strlen($ipBin) * 8) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($wholeBytes && strncmp($ipBin, $subnetBin, $wholeBytes) !== 0) {
            return false;
        }

        if ($remainingBits) {
            $mask = chr((0xFF << (8 - $remainingBits)) & 0xFF);

            if ((($ipBin[$wholeBytes] ^ $subnetBin[$wholeBytes]) & $mask) !== "\0") {
                return false;
            }
        }

        return true;
    }

    public static function mdToHtml($markdown)
    {
        // Milkdown keeps a line's edge spaces as "&#x20;", which safe mode would print as text; fcom drops them too.
        $markdown = str_replace('&#x20;', '', (string) $markdown);

        // Safe mode escapes HTML typed into the markdown; kses then holds the rest to post HTML.
        $html = (new Parsedown())->setSafeMode(true)->setBreaksEnabled(true)->text($markdown);

        // The editor's image block keeps its size ratio in the alt ("0.43") and its caption in the title.
        $html = preg_replace_callback('/<img\b[^>]*>/', function ($match) {
            if (!preg_match('/ alt="(\d+(?:\.\d+)?)"/', $match[0], $alt)) {
                return $match[0];
            }

            $caption = preg_match('/ title="([^"]*)"/', $match[0], $title) ? $title[1] : '';
            // The editor centres the block at that share of the column; it reads 0 as 1 and never widens past the column.
            $width = round(min((float) $alt[1] ?: 1, 1) * 100);

            return str_replace($alt[0], ' alt="' . $caption . '" style="display: block; margin: 0 auto; max-width: ' . $width . '%;"', $match[0]);
        }, $html);

        return wp_kses_post($html);
    }
}
