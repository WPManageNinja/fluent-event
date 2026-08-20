<?php

namespace FluentEvent\App\Utils;

use FluentEvent\Framework\Support\Sanitizer;

class CustomSanitizer extends Sanitizer
{
    public static function webUrl($arg)
    {
        return esc_url_raw(trim((string) $arg), ['http', 'https']) ?: '';
    }
}
