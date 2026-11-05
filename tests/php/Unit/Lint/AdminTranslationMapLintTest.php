<?php

namespace FluentEvent\Tests\Unit\Lint;

use FluentEvent\Tests\TestCase;

/**
 * The admin $t() only translates keys in config/i18n.php, so a string missing there stays English in every locale.
 */
class AdminTranslationMapLintTest extends TestCase
{
    public function test_every_admin_string_is_in_the_translation_map_and_every_entry_is_used()
    {
        $map = (include FLUENT_EVENT_PLUGIN_DIR . 'config/i18n.php')['translations']['admin'];
        $quoted = "'((?:[^'\\\\]|\\\\.)*)'";
        $used = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(FLUENT_EVENT_PLUGIN_DIR . 'resources/admin'));

        foreach ($files as $file) {
            if (!preg_match('/\.(vue|js)$/', $file->getFilename())) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            preg_match_all('/\$t\(\s*' . $quoted . '/', $source, $singles);
            preg_match_all('/\$_n\(\s*' . $quoted . '\s*,\s*' . $quoted . '/', $source, $plurals);

            foreach (array_merge($singles[1], $plurals[1], $plurals[2]) as $key) {
                $used[stripslashes($key)] = true;
            }
        }

        $this->assertNotEmpty($used);
        $this->assertSame([], array_values(array_diff(array_keys($used), array_keys($map))), 'Used with $t() but missing from config/i18n.php');
        $this->assertSame([], array_values(array_diff(array_keys($map), array_keys($used))), 'In config/i18n.php but used nowhere');
    }
}
