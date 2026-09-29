<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Meta;

class Settings
{
    const GENERAL = 'general';
    const EMAIL = 'email';
    const WP_OPTION_KEY = 'fe_settings';

    const DEFAULTS = [
        self::GENERAL => [
            'default_events_view'      => 'timeline',
            'dark_mode'                => 'yes',
        ],
        self::EMAIL => [
            'from_name'      => '',
            'from_email'     => '',
            'reply_to_name'  => '',
            'reply_to_email' => '',
            'email_footer'   => '',
            'reminder_hours' => 24,
        ],
    ];

    protected static $stored = [];

    protected static function getStored($key)
    {
        if ($key === self::GENERAL) {
            // only general settings are stored in wp_options
            $stored = get_option(self::WP_OPTION_KEY, []);
        } else {
            // other settings are stored in fe_meta, read once per request per site
            $cacheKey = get_current_blog_id() . ':' . $key;

            if (!array_key_exists($cacheKey, self::$stored)) {
                $row = Meta::query()
                    ->where('object_type', Meta::OBJECT_TYPE_OPTION)
                    ->byKey($key)
                    ->first();

                self::$stored[$cacheKey] = $row ? $row->value : [];
            }

            $stored = self::$stored[$cacheKey];
        }

        return is_array($stored) ? $stored : [];
    }

    public static function get($key = self::GENERAL)
    {
        return array_merge($defaults = self::DEFAULTS[$key] ?? [], array_intersect_key(
            self::getStored($key), $defaults
        ));
    }

    public static function update($key, array $values)
    {
        $settings = array_merge(
            $defaults = self::DEFAULTS[$key] ?? [],
            array_intersect_key(self::getStored($key), $defaults),
            array_intersect_key($values, $defaults)
        );

        if ($key === self::GENERAL) {
            update_option(self::WP_OPTION_KEY, $settings, true);
        } else {
            Meta::query()->updateOrCreate([
                'object_type' => Meta::OBJECT_TYPE_OPTION,
                'meta_key'    => $key,
            ], [
                'value' => $settings,
            ]);

            self::$stored[get_current_blog_id() . ':' . $key] = $settings;
        }

        return $settings;
    }

}
