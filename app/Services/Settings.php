<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Meta;
use FluentEvent\Framework\Support\Arr;

class Settings
{
    const GENERAL = 'general';
    const EMAIL = 'email';
    const EMAIL_NOTIFICATIONS = 'email_notifications';
    const WP_OPTION_KEY = 'fe_settings';

    const UNIT_MINUTES = 'minutes';
    const UNIT_HOURS = 'hours';
    const UNIT_DAYS = 'days';
    const REMINDER_UNITS = [
        self::UNIT_MINUTES => MINUTE_IN_SECONDS,
        self::UNIT_HOURS   => HOUR_IN_SECONDS,
        self::UNIT_DAYS    => DAY_IN_SECONDS,
    ];
    const MAX_REMINDER_TIME = 30 * DAY_IN_SECONDS;

    const DEFAULTS = [
        self::GENERAL => [
            'default_events_view'       => 'timeline',
            'disable_event_view_switch' => 'no',
        ],
        self::EMAIL => [
            'from_name'      => '',
            'from_email'     => '',
            'reply_to_name'  => '',
            'reply_to_email' => '',
            'email_footer'   => '',
        ],
        self::EMAIL_NOTIFICATIONS => [
            'registration_confirmation' => [
                'enabled' => true,
            ],
            'registration_cancellation' => [
                'enabled' => true,
            ],
            'registration_waitlist' => [
                'enabled' => true,
            ],
            'reminder_to_guest' => [
                'enabled' => true,
                'times'   => [
                    ['value' => 24, 'unit' => self::UNIT_HOURS],
                ],
            ],
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

    public static function reminderInSeconds($reminder)
    {
        return (int) Arr::get($reminder, 'value') * (self::REMINDER_UNITS[Arr::get($reminder, 'unit')] ?? 0);
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
