<?php

namespace FluentEvent\Tests\Unit\Lint;

use FluentEvent\Tests\TestCase;

/**
 * Options API only (CLAUDE.md §3). The edit-time scanner checks changed lines, so an old file could keep a <script setup>.
 */
class VueOptionsApiLintTest extends TestCase
{
    public function test_no_vue_component_uses_script_setup()
    {
        $offenders = [];
        $checked = 0;

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(FLUENT_EVENT_PLUGIN_DIR . 'resources'));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }

            $checked++;

            if (preg_match('/<script\b[^>]*\bsetup\b/', file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(FLUENT_EVENT_PLUGIN_DIR, '', $file->getPathname());
            }
        }

        $this->assertGreaterThan(0, $checked);
        $this->assertSame([], $offenders);
    }
}
