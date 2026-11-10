<?php

namespace FluentEvent\App\Utils;

use FluentEvent\Framework\Support\Sanitizer;

class CustomSanitizer extends Sanitizer
{
    // sanitize_email() and the email rule's is_email() both throw on a list, a 500 that names the file
    // and line under WP_DEBUG; empty, it fails as a missing email.
    public static function sanitizeEmail($arg)
    {
        return is_scalar($arg) ? sanitize_email($arg) : '';
    }

    public static function webUrl($arg)
    {
        // Cast to a string, a list reads "Array" and saves as http://Array; left as is, the url rule rejects it.
        if (!is_scalar($arg)) {
            return $arg;
        }

        $url = esc_url_raw(trim((string) $arg), ['http', 'https']) ?: '';

        // esc_url_raw leaves non-ASCII as is (an upload named "Feature – 2"), and the url rule
        // rejects it. Encode it past the host, so an international domain name stays intact.
        $scheme = strpos($url, '://');
        $pathStart = $scheme === false ? false : strpos($url, '/', $scheme + 3);

        if ($pathStart === false) {
            return $url;
        }

        return substr($url, 0, $pathStart) . preg_replace_callback('/[^\x00-\x7F]+/', function ($match) {
            return rawurlencode($match[0]);
        }, substr($url, $pathStart));
    }
}
