<?php

namespace FluentEvent\Tests\Unit\Lint;

use FluentEvent\Tests\TestCase;

/**
 * composer.json ships in the zip (build.sh PAYLOAD), so its metadata must match the plugin's GPL license.
 */
class ComposerLicenseLintTest extends TestCase
{
    public function test_shipped_composer_json_carries_the_plugin_license_and_author()
    {
        $composer = json_decode(file_get_contents(FLUENT_EVENT_PLUGIN_DIR . 'composer.json'), true);

        $this->assertSame('GPL-2.0-or-later', $composer['license']);
        $this->assertStringNotContainsString('WPFluent', $composer['description']);
        $this->assertSame([['name' => 'WPManageNinja LLC', 'homepage' => 'https://wpmanageninja.com/']], $composer['authors']);
    }
}
