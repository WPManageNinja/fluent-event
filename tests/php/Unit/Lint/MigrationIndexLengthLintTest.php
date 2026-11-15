<?php

namespace FluentEvent\Tests\Unit\Lint;

use FluentEvent\Tests\TestCase;

/**
 * Older MySQL and MariaDB limit an indexed column to 767 bytes: 191 characters in utf8mb4. A longer
 * VARCHAR in any index makes the server refuse the whole CREATE TABLE, and dbDelta() reports nothing.
 */
class MigrationIndexLengthLintTest extends TestCase
{
    const MAX_INDEXED_CHARS = 191;

    public function test_no_indexed_varchar_is_longer_than_191_characters()
    {
        $files = glob(FLUENT_EVENT_PLUGIN_DIR . 'database/Migrations/*.php');
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            preg_match_all('/"CREATE TABLE.*?";/s', file_get_contents($file), $statements);

            foreach ($statements[0] as $sql) {
                preg_match_all('/`(\w+)` VARCHAR\((\d+)\)/i', $sql, $columns, PREG_SET_ORDER);
                $lengths = [];
                foreach ($columns as $column) {
                    $lengths[$column[1]] = (int) $column[2];
                }

                preg_match_all('/(?:KEY|INDEX)\s+`\w+`\s*\(([^)]*)\)/i', $sql, $indexes);

                foreach ($indexes[1] as $indexColumns) {
                    preg_match_all('/`(\w+)`/', $indexColumns, $names);

                    foreach ($names[1] as $name) {
                        $this->assertLessThanOrEqual(
                            self::MAX_INDEXED_CHARS,
                            $lengths[$name] ?? 0,
                            basename($file) . ": indexed column `$name` is longer than " . self::MAX_INDEXED_CHARS . ' characters'
                        );
                    }
                }
            }
        }
    }
}
