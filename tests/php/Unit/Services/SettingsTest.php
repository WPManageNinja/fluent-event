<?php

namespace FluentEvent\Tests\Unit\Services;

use FluentEvent\App\Services\Settings;
use FluentEvent\Tests\TestCase;

/**
 * General settings live in wp_options and run against the option stub here.
 * The fe_meta-backed sections are covered in the integration tier.
 */
class SettingsTest extends TestCase
{
    /**
     * @dataProvider reminders
     */
    public function test_a_reminder_converts_to_seconds($reminder, $seconds)
    {
        $this->assertSame($seconds, Settings::reminderInSeconds($reminder));
    }

    public function reminders()
    {
        return [
            'minutes'         => [['value' => 30, 'unit' => 'minutes'], 1800],
            'hours'           => [['value' => 24, 'unit' => 'hours'], 86400],
            'days'            => [['value' => 2, 'unit' => 'days'], 172800],
            'unknown unit'    => [['value' => 2, 'unit' => 'weeks'], 0],
            'missing value'   => [['unit' => 'hours'], 0],
            'string number'   => [['value' => '3', 'unit' => 'hours'], 10800],
        ];
    }

    public function test_the_longest_reminder_is_thirty_days()
    {
        $this->assertSame(30 * DAY_IN_SECONDS, Settings::MAX_REMINDER_TIME);
    }

    public function test_out_of_the_box_guests_get_confirmations_cancellations_and_a_day_ahead_reminder()
    {
        $emails = Settings::DEFAULTS[Settings::EMAIL_NOTIFICATIONS];

        $this->assertTrue($emails['registration_confirmation']['enabled']);
        $this->assertTrue($emails['registration_cancellation']['enabled']);
        $this->assertSame([['value' => 24, 'unit' => 'hours']], $emails['reminder_to_guest']['times']);
    }

    public function test_general_settings_fall_back_to_defaults_when_nothing_is_stored()
    {
        $this->assertSame(Settings::DEFAULTS[Settings::GENERAL], Settings::get());
    }

    public function test_stored_general_settings_override_defaults_and_unknown_keys_are_dropped()
    {
        update_option(Settings::WP_OPTION_KEY, ['default_events_view' => 'cards', 'legacy_flag' => 'yes']);

        $settings = Settings::get(Settings::GENERAL);

        $this->assertSame('cards', $settings['default_events_view']);
        $this->assertArrayNotHasKey('legacy_flag', $settings);
    }

    public function test_a_corrupt_stored_value_reads_as_defaults()
    {
        update_option(Settings::WP_OPTION_KEY, 'not-an-array');

        $this->assertSame(Settings::DEFAULTS[Settings::GENERAL], Settings::get());
    }

    public function test_updating_general_settings_stores_only_known_keys()
    {
        update_option(Settings::WP_OPTION_KEY, ['default_events_view' => 'cards']);

        $saved = Settings::update(Settings::GENERAL, ['disable_event_view_switch' => 'yes', 'injected' => 'x']);

        $this->assertSame(['default_events_view' => 'cards', 'disable_event_view_switch' => 'yes', 'events_page_id' => 0, 'event_slug' => 'event'], $saved);
        $this->assertSame($saved, get_option(Settings::WP_OPTION_KEY));
    }
}
