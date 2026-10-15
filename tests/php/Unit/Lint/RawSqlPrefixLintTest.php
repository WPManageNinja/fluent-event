<?php

namespace FluentEvent\Tests\Unit\Lint;

use FluentEvent\Tests\TestCase;

/**
 * Runs tests/lint/raw-sql-prefix.php. The unit tier never executes SQL, so an
 * unprefixed table in raw SQL can only be caught statically. A lint that matches
 * nothing also says OK, so it must first prove it fires on the fixture.
 */
class RawSqlPrefixLintTest extends TestCase
{
    private function runLint($target = null)
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(FLUENT_EVENT_PLUGIN_DIR . 'tests/lint/raw-sql-prefix.php');

        if ($target !== null) {
            $command .= ' ' . escapeshellarg($target);
        }

        exec($command . ' 2>&1', $output, $exit);

        return ['exit' => $exit, 'output' => implode("\n", $output)];
    }

    private function runOnFixture()
    {
        return $this->runLint(FLUENT_EVENT_PLUGIN_DIR . 'tests/lint/fixtures');
    }

    public function test_it_fires_on_every_bad_shape_in_the_fixture()
    {
        $result = $this->runOnFixture();

        $this->assertSame(1, $result['exit'], $result['output']);
        $this->assertStringContainsString('5 violation(s)', $result['output']);

        foreach (['fe_guests.', 'fe_calendars.', 'fcom_posts.', 'fe_meta.', 'fe_event_details.'] as $identifier) {
            $this->assertStringContainsString($identifier, $result['output']);
        }
    }

    public function test_it_reports_a_multi_line_call_on_the_literal_line()
    {
        $output = $this->runOnFixture()['output'];

        // The call opens on line 22; the offending literal sits on 23.
        $this->assertStringContainsString('bad-raw-prefix.php:23', $output);
        $this->assertStringNotContainsString('bad-raw-prefix.php:22', $output);
    }

    public function test_it_does_not_flag_correctly_prefixed_look_alikes()
    {
        $this->assertStringNotContainsString('fe_ticket_types', $this->runOnFixture()['output']);
    }

    public function test_it_actually_scans_the_plugin_source()
    {
        preg_match('/scanned (\d+) PHP files/', $this->runLint()['output'], $match);

        $this->assertGreaterThan(50, (int) ($match[1] ?? 0), 'Too few files scanned; wrong root?');
    }

    public function test_the_plugin_has_no_unprefixed_tables_in_raw_sql()
    {
        $result = $this->runLint();

        $this->assertSame(0, $result['exit'], $result['output']);
    }

    public function test_it_covers_every_raw_method_the_framework_exposes()
    {
        $lint = file_get_contents(FLUENT_EVENT_PLUGIN_DIR . 'tests/lint/raw-sql-prefix.php');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(FLUENT_EVENT_PLUGIN_DIR . 'vendor/wpfluent/framework/src/WPFluent/Database'));
        $declared = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && preg_match_all('/function\s+([a-zA-Z]*Raw)\b/', file_get_contents($file->getPathname()), $m)) {
                $declared = array_merge($declared, $m[1]);
            }
        }

        $this->assertNotEmpty($declared, 'Found no *Raw methods; detection broke');

        foreach (array_unique($declared) as $method) {
            $this->assertStringContainsString($method, $lint, "The framework exposes {$method}() but the lint does not cover it");
        }
    }
}
