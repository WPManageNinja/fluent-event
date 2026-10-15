<?php
/**
 * Lint: raw SQL must not name a plugin table without its prefix.
 *
 * where()/select()/join() go through the query grammar, which prepends
 * $wpdb->prefix. selectRaw(), whereRaw(), orderByRaw() and friends hand their
 * string to MySQL verbatim, so `fe_guests.status` inside one is an
 * "Unknown column" on every site. Unlike fluent-booking's line-based version,
 * this reads the whole call, so a literal on the line after `selectRaw(` counts.
 *
 * Usage: php tests/lint/raw-sql-prefix.php [path]
 * Exit:  0 clean, 1 violations found
 */

$root = dirname(__DIR__, 2);
$scanDirs = isset($argv[1]) && $argv[1] !== '' ? [rtrim($argv[1], '/')] : ['app', 'Modules', 'database'];

// Every *Raw entry point in vendor/wpfluent/framework/src/WPFluent/Database/.
$rawCall = '/\b(?:selectRaw|fromRaw|whereRaw|orWhereRaw|whereInRaw|whereNotInRaw'
    . '|whereIntegerInRaw|whereIntegerNotInRaw|orWhereIntegerInRaw|orWhereIntegerNotInRaw'
    . '|havingRaw|orHavingRaw|orderByRaw|groupByRaw|raw)\s*\(/i';

// A table-qualified identifier for one of our tables or FluentCommunity's.
$qualified = '/\b(?:fe|fcom)_[a-z0-9_]+\s*\./i';

$literal = '/"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'/s';
$prefixInside = '/\$wpdb->prefix|getTablePrefix\s*\(|\{\$|\$[a-zA-Z_]/';
$prefixBefore = '/(?:\$wpdb->prefix|getTablePrefix\s*\(\s*\)|\$[a-zA-Z_][a-zA-Z0-9_]*)\s*\.\s*$/';

// The text between a call's parentheses, skipping parentheses inside string literals.
$arguments = function ($source, $open) {
    $depth = 0;
    $quote = null;
    $length = strlen($source);

    for ($i = $open; $i < $length; $i++) {
        $char = $source[$i];

        if ($quote !== null) {
            if ($char === '\\') {
                $i++;
            } elseif ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === '"' || $char === "'") {
            $quote = $char;
        } elseif ($char === '(') {
            $depth++;
        } elseif ($char === ')' && --$depth === 0) {
            return substr($source, $open + 1, $i - $open - 1);
        }
    }

    return substr($source, $open + 1);
};

$violations = [];
$scanned = 0;

foreach ($scanDirs as $dir) {
    $target = $dir[0] === '/' ? $dir : $root . '/' . $dir;

    if (!is_dir($target)) {
        continue;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $path = $file->getPathname();

        if (strtolower($file->getExtension()) !== 'php' || strpos($path, '/vendor/') !== false) {
            continue;
        }

        $scanned++;
        $source = file_get_contents($path);

        if (!preg_match_all($rawCall, $source, $calls, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($calls[0] as $call) {
            $open = $call[1] + strlen($call[0]) - 1;
            $args = $arguments($source, $open);

            if (!preg_match_all($literal, $args, $strings, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($strings as $string) {
                $text = isset($string[2]) && $string[2][1] !== -1 ? $string[2][0] : $string[1][0];

                if (!preg_match($qualified, $text, $hit) || preg_match($prefixInside, $text)) {
                    continue;
                }

                if (preg_match($prefixBefore, substr($args, 0, $string[0][1]))) {
                    continue;
                }

                $violations[] = [
                    'file'  => str_replace($root . '/', '', $path),
                    'line'  => substr_count($source, "\n", 0, $open + 1 + $string[0][1]) + 1,
                    'ident' => trim($hit[0]),
                ];
            }
        }
    }
}

echo "raw-sql-prefix: scanned {$scanned} PHP files\n";

if (!$violations) {
    echo "OK - no unprefixed table-qualified identifiers inside raw SQL.\n";
    exit(0);
}

echo "\nFAIL - " . count($violations) . " violation(s):\n\n";

foreach ($violations as $violation) {
    echo "  {$violation['file']}:{$violation['line']}  unprefixed identifier: {$violation['ident']}\n";
}

echo "\nRaw SQL bypasses the query grammar. Resolve the prefix explicitly:\n";
echo "  global \$wpdb; \$table = \$wpdb->prefix . 'fe_guests';\n";
echo "  ->selectRaw(\"COUNT(`{\$table}`.`id`) AS total\")\n";

exit(1);
