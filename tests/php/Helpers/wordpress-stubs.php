<?php
/**
 * WordPress function stubs for the unit tier, adapted from fluent-booking's.
 *
 * Only what code calls at load time or in nearly every test lives here; a test
 * that wants control mocks the function with Brain\Monkey instead. Sanitizer
 * stubs model the real pipeline, not a bare strip_tags(), so an assertion that a
 * payload is gone means what it says. URL escaping is NOT modelled — URL
 * sanitizer tests belong in the integration tier, where the real esc_url_raw() runs.
 */

if (!function_exists('esc_sql')) {
    function esc_sql($data)
    {
        if (is_array($data)) {
            return array_map('esc_sql', $data);
        }

        return addslashes($data);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('wp_slash')) {
    function wp_slash($value)
    {
        if (is_array($value)) {
            return array_map('wp_slash', $value);
        }

        return is_string($value) ? addslashes($value) : $value;
    }
}

if (!function_exists('wp_check_invalid_utf8')) {
    function wp_check_invalid_utf8($string, $strip = false)
    {
        $string = (string) $string;

        if ($string === '') {
            return '';
        }

        return mb_check_encoding($string, 'UTF-8') ? $string : '';
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $removeBreaks = false)
    {
        $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $string);
        $string = strip_tags($string);

        if ($removeBreaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }

        return trim($string);
    }
}

if (!function_exists('sanitize_text_field')) {
    // Mirrors _sanitize_text_fields(): script CONTENT goes, whitespace collapses, stray % octets go.
    function sanitize_text_field($str)
    {
        if (is_object($str) || is_array($str)) {
            return '';
        }

        $filtered = wp_check_invalid_utf8((string) $str);

        if (strpos($filtered, '<') !== false) {
            $filtered = wp_strip_all_tags($filtered, true);
            $filtered = str_replace("<\n", "&lt;\n", $filtered);
        }

        $filtered = trim(preg_replace('/[\r\n\t ]+/', ' ', $filtered));

        $found = false;
        while (preg_match('/%[a-f0-9]{2}/i', $filtered, $match)) {
            $filtered = str_replace($match[0], '', $filtered);
            $found = true;
        }

        if ($found) {
            $filtered = trim(preg_replace('/ +/', ' ', $filtered));
        }

        return $filtered;
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str)
    {
        if (is_object($str) || is_array($str)) {
            return '';
        }

        $str = wp_check_invalid_utf8((string) $str);

        if (strpos($str, '<') !== false) {
            $str = wp_strip_all_tags($str);
        }

        return trim($str);
    }
}

if (!function_exists('esc_url_raw')) {
    // Character filter only: no protocol allowlist. See the file docblock.
    function esc_url_raw($url, $protocols = null)
    {
        return preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', trim((string) $url));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title, $fallback = '')
    {
        $title = strtolower(trim(strip_tags((string) $title)));
        $title = preg_replace('/[^a-z0-9_\-\s]/', '', $title);
        $title = trim(preg_replace('/[\s_]+/', '-', $title), '-');

        return $title === '' ? $fallback : $title;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        $email = trim((string) $email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('is_email')) {
    function is_email($email)
    {
        return (bool) filter_var((string) $email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color)
    {
        if ($color === '') {
            return '';
        }

        return preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color) ? $color : null;
    }
}

if (!function_exists('rest_sanitize_boolean')) {
    function rest_sanitize_boolean($value)
    {
        if (is_string($value)) {
            $value = strtolower($value);

            if (in_array($value, ['false', '0'], true)) {
                return false;
            }
        }

        return (bool) $value;
    }
}

if (!function_exists('absint')) {
    function absint($number)
    {
        return abs((int) $number);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = [])
    {
        if (is_object($args)) {
            $args = get_object_vars($args);
        } elseif (is_string($args)) {
            parse_str($args, $args);
        }

        return is_array($defaults) ? array_merge($defaults, (array) $args) : (array) $args;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null)
    {
        return 'https://example.com' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin')
    {
        return 'https://example.com/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false)
    {
        return array_key_exists($option, $GLOBALS['feTestOptions'])
            ? $GLOBALS['feTestOptions'][$option]
            : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null)
    {
        $GLOBALS['feTestOptions'][$option] = $value;

        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($option, $value = '', $deprecated = '', $autoload = 'yes')
    {
        if (array_key_exists($option, $GLOBALS['feTestOptions'])) {
            return false;
        }

        $GLOBALS['feTestOptions'][$option] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option)
    {
        unset($GLOBALS['feTestOptions'][$option]);

        return true;
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '', $force = false, &$found = null)
    {
        $cacheKey = $group . ':' . $key;
        $found = array_key_exists($cacheKey, $GLOBALS['feTestObjectCache']);

        return $found ? $GLOBALS['feTestObjectCache'][$cacheKey] : false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0)
    {
        $GLOBALS['feTestObjectCache'][$group . ':' . $key] = $data;

        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '')
    {
        unset($GLOBALS['feTestObjectCache'][$group . ':' . $key]);

        return true;
    }
}

if (!function_exists('wp_timezone_string')) {
    function wp_timezone_string()
    {
        return 'UTC';
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone()
    {
        return new DateTimeZone('UTC');
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = 0)
    {
        if ($type === 'timestamp' || $type === 'U') {
            return time();
        }

        return gmdate($type === 'mysql' ? 'Y-m-d H:i:s' : $type);
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp = false, $gmt = false)
    {
        return gmdate($format, $timestamp === false ? time() : $timestamp);
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $specialChars = true, $extraSpecialChars = false)
    {
        return substr(str_repeat('aA1bB2cC3dD4', (int) ceil($length / 12)), 0, $length);
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit($string)
    {
        return rtrim((string) $string, '/\\');
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string)
    {
        return untrailingslashit($string) . '/';
    }
}

if (!class_exists('WP_Error')) {
    // Keeps a list of messages per code and returns the first, as the real class does.
    class WP_Error
    {
        protected $errors = [];

        protected $error_data = [];

        public function __construct($code = '', $message = '', $data = '')
        {
            if ($code !== '') {
                $this->add($code, $message, $data);
            }
        }

        public function add($code, $message = '', $data = '')
        {
            $this->errors[$code][] = $message;

            if ($data !== '') {
                $this->error_data[$code] = $data;
            }
        }

        public function get_error_code()
        {
            $codes = array_keys($this->errors);

            return $codes ? $codes[0] : '';
        }

        public function get_error_message($code = '')
        {
            $code = $code === '' ? $this->get_error_code() : $code;

            return $this->errors[$code][0] ?? '';
        }

        public function get_error_data($code = '')
        {
            $code = $code === '' ? $this->get_error_code() : $code;

            return $this->error_data[$code] ?? null;
        }

        public function has_errors()
        {
            return (bool) $this->errors;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof \WP_Error;
    }
}
