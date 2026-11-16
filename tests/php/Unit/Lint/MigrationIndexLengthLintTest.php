<?php

namespace FluentEvent\Tests\Unit\Lint;

use FluentEvent\Tests\TestCase;

/**
 * Older MySQL and MariaDB limit an indexed column to 767 bytes: 191 characters in utf8mb4. MyISAM also
 * limits a whole index to 1000 bytes. An index over either limit makes the server refuse the whole
 * CREATE TABLE, and dbDelta() reports nothing.
 */
class MigrationIndexLengthLintTest extends TestCase
{
    const MAX_INDEXED_CHARS = 191;
    const MAX_INDEX_BYTES = 1000;

    public function test_no_indexed_varchar_is_longer_than_191_characters()
    {
        foreach ($this->indexes() as $index) {
            foreach ($index['columns'] as $name => $column) {
                $this->assertLessThanOrEqual(
                    self::MAX_INDEXED_CHARS,
                    $column['chars'],
                    $index['file'] . ": indexed column `$name` is longer than " . self::MAX_INDEXED_CHARS . ' characters'
                );
            }
        }
    }

    public function test_no_index_is_longer_than_1000_bytes()
    {
        foreach ($this->indexes() as $index) {
            $bytes = array_sum(array_column($index['columns'], 'bytes'));

            $this->assertLessThanOrEqual(
                self::MAX_INDEX_BYTES,
                $bytes,
                $index['file'] . ': index `' . $index['name'] . "` takes $bytes bytes in utf8mb4"
            );
        }
    }

    /**
     * @return array<int, array{file: string, name: string, columns: array<string, array{chars: int, bytes: int}>}>
     */
    private function indexes()
    {
        $files = glob(FLUENT_EVENT_PLUGIN_DIR . 'database/Migrations/*.php');
        $this->assertNotEmpty($files);

        $indexes = [];

        foreach ($files as $file) {
            preg_match_all('/"CREATE TABLE.*?";/s', file_get_contents($file), $statements);

            foreach ($statements[0] as $sql) {
                preg_match_all('/^\s*`(\w+)`\s+(\w+)(?:\((\d+)\))?/mi', $sql, $definitions, PREG_SET_ORDER);
                $columns = [];
                foreach ($definitions as $definition) {
                    $columns[$definition[1]] = [$definition[2], (int) ($definition[3] ?? 0)];
                }

                preg_match_all('/(?:KEY|INDEX)\s+`(\w+)`\s*\(([^)]*)\)/i', $sql, $keys, PREG_SET_ORDER);

                foreach ($keys as $key) {
                    preg_match_all('/`(\w+)`/', $key[2], $names);

                    $indexColumns = [];
                    foreach ($names[1] as $name) {
                        $this->assertArrayHasKey($name, $columns, basename($file) . ": index `{$key[1]}` names an unknown column `$name`");
                        $indexColumns[$name] = $this->size($columns[$name][0], $columns[$name][1]);
                    }

                    $indexes[] = ['file' => basename($file), 'name' => $key[1], 'columns' => $indexColumns];
                }
            }
        }

        return $indexes;
    }

    /**
     * @return array{chars: int, bytes: int}
     */
    private function size($type, $length)
    {
        $type = strtoupper($type);

        if (in_array($type, ['VARCHAR', 'CHAR'], true)) {
            // utf8mb4 stores up to 4 bytes a character, and a VARCHAR adds 2 bytes for its length.
            return ['chars' => $length, 'bytes' => $length * 4 + ($type === 'VARCHAR' ? 2 : 0)];
        }

        $bytes = [
            'TINYINT'   => 1,
            'SMALLINT'  => 2,
            'INT'       => 4,
            'BIGINT'    => 8,
            'DATE'      => 3,
            'DATETIME'  => 8,
            'TIMESTAMP' => 4,
            'DECIMAL'   => 9,
        ];

        $this->assertArrayHasKey($type, $bytes, "Add the index size of column type $type to this test");

        return ['chars' => 0, 'bytes' => $bytes[$type]];
    }
}
