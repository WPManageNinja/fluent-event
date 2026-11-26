<?php

namespace FluentEvent\Tests\Unit\Build;

use PHPUnit\Framework\TestCase;

/**
 * The WordPress.org zip leaves the self-hosted updater out, so its readme must not describe it.
 * Runs build.sh --wporg on a copy of the payload with a stub assets/ build.
 */
class WporgReadmeBuildTest extends TestCase
{
    private $dir;

    protected function setUp(): void
    {
        foreach (['bash', 'composer', 'zip', 'unzip', 'node', 'rsync'] as $tool) {
            if (!trim((string) shell_exec('command -v ' . $tool))) {
                $this->markTestSkipped("$tool is not installed.");
            }
        }

        $root = FLUENT_EVENT_PLUGIN_DIR;
        $this->dir = sys_get_temp_dir() . '/fevent-build-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/assets', 0777, true);
        mkdir($this->dir . '/resources/vite', 0777, true);

        $paths = 'build.sh fluent-event.php readme.txt composer.json app boot config database language Modules vendor';
        exec('cd ' . escapeshellarg($root) . ' && rsync -a ' . $paths . ' ' . escapeshellarg($this->dir . '/'), $out, $code);
        $this->assertSame(0, $code, 'Could not copy the payload.');
        copy($root . 'resources/vite/rtl-css.js', $this->dir . '/resources/vite/rtl-css.js');

        file_put_contents($this->dir . '/assets/admin.js', '');
        file_put_contents($this->dir . '/assets/manifest.json', json_encode(['admin' => ['file' => 'admin.js']]));
    }

    protected function tearDown(): void
    {
        if ($this->dir) {
            exec('rm -rf ' . escapeshellarg($this->dir));
        }
    }

    public function test_wporg_readme_names_only_google_calendar_as_external_service()
    {
        exec('cd ' . escapeshellarg($this->dir) . ' && bash build.sh --wporg 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));

        $zips = glob($this->dir . '/builds/*-wporg.zip');
        $this->assertCount(1, $zips);
        $readme = shell_exec('unzip -p ' . escapeshellarg($zips[0]) . ' fluent-event/readme.txt');

        $this->assertStringNotContainsString('fluentapi.wpmanageninja.com', $readme);
        $this->assertStringNotContainsStringIgnoringCase('update server', $readme);
        $this->assertStringContainsString('FluentEvent connects to one external service: Google Calendar', $readme);
        $this->assertStringContainsString('= Google Calendar =', $readme);
    }
}
