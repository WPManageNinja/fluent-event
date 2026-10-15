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
    const FIXTURE = 'tests/lint/fixtures/bad-raw-prefix.php';

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

    /**
     * @return int[]
     */
    private function markedLines()
    {
        $lines = [];

        foreach (file(FLUENT_EVENT_PLUGIN_DIR . self::FIXTURE) as $index => $line) {
            if (preg_match('/(?:\/\/|--) must-fire\s*$/', $line)) {
                $lines[] = $index + 1;
            }
        }

        return $lines;
    }

    public function test_the_fixture_is_valid_php()
    {
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(FLUENT_EVENT_PLUGIN_DIR . self::FIXTURE) . ' 2>&1', $output, $exit);

        $this->assertSame(0, $exit, implode("\n", $output));
    }

    public function test_it_reports_exactly_the_marked_lines_of_the_fixture()
    {
        // Equality, not containment: a missed shape and a false positive on a look-alike both fail here.
        $marked = $this->markedLines();
        $result = $this->runOnFixture();

        preg_match_all('/bad-raw-prefix\.php:(\d+)/', $result['output'], $m);
        $reported = array_map('intval', $m[1]);

        $this->assertGreaterThanOrEqual(13, count($marked), 'The fixture lost its must-fire markers');
        $this->assertSame(1, $result['exit'], $result['output']);
        $this->assertSame($marked, $reported, $result['output']);
    }

    public function test_a_hardcoded_prefix_is_named_as_one()
    {
        $this->assertStringContainsString('hardcoded prefix: wp_fe_guests', $this->runOnFixture()['output']);
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
