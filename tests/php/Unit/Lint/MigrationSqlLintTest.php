<?php

namespace FluentEvent\Tests\Unit\Lint;

use FluentEvent\Tests\TestCase;

/**
 * dbDelta() splits its input on every ";", so one inside a quoted COMMENT or DEFAULT cuts the
 * CREATE TABLE in two and the table is never made on a fresh install.
 */
class MigrationSqlLintTest extends TestCase
{
    public function test_no_create_table_has_a_semicolon_inside_a_quoted_value()
    {
        $files = glob(FLUENT_EVENT_PLUGIN_DIR . 'database/Migrations/*.php');
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $source = file_get_contents($file);

            // Only the table migrators build a CREATE TABLE; LegacyPrefixMigrator renames tables instead.
            if (strpos($source, '$tableName') === false) {
                continue;
            }

            preg_match_all('/"CREATE TABLE.*?";/s', $source, $statements);
            $this->assertNotEmpty($statements[0], basename($file) . ' has no CREATE TABLE to check');

            foreach ($statements[0] as $sql) {
                $this->assertDoesNotMatchRegularExpression("/'[^']*;[^']*'/", $sql, basename($file));
            }
        }
    }
}
