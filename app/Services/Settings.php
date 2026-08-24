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
            'date_format'              => '',
            'time_format'              => '',
            'dark_mode'                => 'yes',
            'remove_data_on_uninstall' => 'no',
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

    protected static function getStored($key)
    {
        if ($key === self::GENERAL) {
            // only general settings are stored in wp_options
            $stored = get_option(self::WP_OPTION_KEY, []);
        } else {
            // other settings are stored in fe_meta
            $row = Meta::query()
                ->where('object_type', Meta::OBJECT_TYPE_OPTION)
                ->byKey($key)
                ->first();

            $stored = $row ? $row->value : [];
        }

        return is_array($stored) ? $stored : [];
    }

    public static function get($key = self::GENERAL)
    {
        $settings = array_merge($defaults = self::DEFAULTS[$key] ?? [], array_intersect_key(
            self::getStored($key), $defaults
        ));

        if ($key === self::GENERAL) {
            // fallback to WP's own formats
            $settings['date_format'] = $settings['date_format'] ?: get_option('date_format', 'F j, Y');
            $settings['time_format'] = $settings['time_format'] ?: get_option('time_format', 'g:i a');
        }

        return $settings;
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
        }

        return $settings;
    }

}
