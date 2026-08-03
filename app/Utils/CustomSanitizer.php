<?php

namespace FluentEvent\App\Utils;

use BadMethodCallException;
use FluentEvent\Framework\Support\Sanitizer;

class CustomSanitizer extends Sanitizer
{
    const SANITIZE_EMAIL = 'sanitize_email';
    const SANITIZE_FILE_NAME = 'sanitize_file_name';
    const SANITIZE_HEX_COLOR = 'sanitize_hex_color';
    const SANITIZE_HTML_CLASS = 'sanitize_html_class';
    const SANITIZE_KEY = 'sanitize_key';
    const SANITIZE_MIME_TYPE = 'sanitize_mime_type';
    const SANITIZE_SQL_ORDERBY = 'sanitize_sql_orderby';
    const SANITIZE_TEXT_FIELD = 'sanitize_text_field';
    const SANITIZE_TEXTAREA_FIELD = 'sanitize_textarea_field';
    const SANITIZE_TITLE = 'sanitize_title';
    const SANITIZE_URL = 'sanitize_url';
    const SANITIZE_USER = 'sanitize_user';
    const WP_KSES_POST = 'wp_kses_post';
    const ABSINT = 'absint';
    const INTVAL = 'intval';
    const FLOATVAL = 'floatval';

    const SANITIZERS = [
        'email'          => self::SANITIZE_EMAIL,
        'fileName'       => self::SANITIZE_FILE_NAME,
        'hexColor'       => self::SANITIZE_HEX_COLOR,
        'htmlClass'      => self::SANITIZE_HTML_CLASS,
        'key'            => self::SANITIZE_KEY,
        'mimeType'       => self::SANITIZE_MIME_TYPE,
        'sqlOrderby'     => self::SANITIZE_SQL_ORDERBY,
        'textField'      => self::SANITIZE_TEXT_FIELD,
        'textareaField'  => self::SANITIZE_TEXTAREA_FIELD,
        'title'          => self::SANITIZE_TITLE,
        'url'            => self::SANITIZE_URL,
        'user'           => self::SANITIZE_USER,
        'ksesPost'       => self::WP_KSES_POST,
        'absint'         => self::ABSINT,
        'intval'         => self::INTVAL,
        'floatval'       => self::FLOATVAL,
    ];

    public function __call($name, $arguments)
    {
        if (!isset(static::SANITIZERS[$name])) {
            throw new BadMethodCallException(
                sprintf('Unknown sanitizer "%s" on %s.', $name, static::class)
            );
        }

        return call_user_func_array(static::SANITIZERS[$name], $arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return (new static)->$name(...$arguments);
    }
}
