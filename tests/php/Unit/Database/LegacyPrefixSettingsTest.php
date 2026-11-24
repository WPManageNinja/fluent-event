<?php

namespace FluentEvent\Tests\Unit\Database;

use FluentEvent\Database\Migrations\LegacyPrefixMigrator;
use FluentEvent\Tests\TestCase;

/**
 * While the `fe_` → `fevent` move is paused, the new settings option does not exist yet. Reads fall
 * back to the old option, so the admin sees the site's own settings and a save keeps them.
 */
class LegacyPrefixSettingsTest extends TestCase
{
    public function test_a_missing_new_option_reads_the_old_settings()
    {
        update_option(LegacyPrefixMigrator::OLD_SETTINGS_OPTION, ['event_slug' => 'happenings', 'events_page_id' => 12]);

        $this->assertSame(
            ['event_slug' => 'happenings', 'events_page_id' => 12],
            LegacyPrefixMigrator::legacySettingsFallback([])
        );
    }

    public function test_without_old_settings_the_caller_default_stands()
    {
        delete_option(LegacyPrefixMigrator::OLD_SETTINGS_OPTION);

        $this->assertSame([], LegacyPrefixMigrator::legacySettingsFallback([]));
        $this->assertFalse(LegacyPrefixMigrator::legacySettingsFallback(false));
    }
}
