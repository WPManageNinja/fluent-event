<?php
/**
 * Lint: raw SQL must not name a table without resolving its prefix.
 *
 * where()/select()/join() go through the query grammar, which prepends
 * $wpdb->prefix. selectRaw(), whereRaw(), orderByRaw() and friends hand their
 * string to MySQL verbatim, so a table named inside one must carry the prefix
 * itself. Flagged: our and FluentCommunity's tables (fe_*, fcom_*) and WordPress
 * core tables left unprefixed, and any hardcoded `wp_` table, which passes on a
 * wp_-prefixed dev site and fails everywhere else.
 *
 * A name counts as a table when it is qualified (`fe_guests.id`, backticks
 * allowed), follows FROM/JOIN/UPDATE/INTO, or opens a fromRaw(). Quoted SQL
 * values are ignored, heredocs are read, and a variable passed to the call is
 * followed back to its assignments in the same function.
 *
 * Usage: php tests/lint/raw-sql-prefix.php [path]
 * Exit:  0 clean, 1 violations found
 */

$root = dirname(__DIR__, 2);
$scanDirs = isset($argv[1]) && $argv[1] !== '' ? [rtrim($argv[1], '/')] : ['app', 'Modules', 'database'];

// Every *Raw entry point in vendor/wpfluent/framework/src/WPFluent/Database/.
$rawCall = '/\b(selectRaw|fromRaw|whereRaw|orWhereRaw|whereInRaw|whereNotInRaw'
    . '|whereIntegerInRaw|whereIntegerNotInRaw|orWhereIntegerInRaw|orWhereIntegerNotInRaw'
    . '|havingRaw|orHavingRaw|orderByRaw|groupByRaw|raw)\s*\(/i';

$coreTables = 'posts|postmeta|users|usermeta|options|comments|commentmeta|terms|termmeta|term_taxonomy|term_relationships|links';
$table = '(?:wp_)?(?:(?:fe|fcom)_[a-z0-9_]+|' . $coreTables . ')(?![a-z0-9_])|wp_[a-z0-9_]+';

// Not after a word char, `$`, `>` or `}`: `{$wpdb->prefix}fe_x` and `{$wpdb->posts}` are resolved.
$qualifiedUse = '/(?<![\w$>}])`?(' . $table . ')`?(?=\s*\.)/i';
$keywordUse = '/\b(?:FROM|JOIN|UPDATE|INTO)\s+`?(' . $table . ')`?(?![\w{$])/i';
$leadingUse = '/^\s*`?(' . $table . ')`?(?![\w{$])/i';

$prefixBefore = '/(?:\$wpdb->(?:base_)?prefix|getTablePrefix\s*\(\s*\)|\$\w*prefix\w*)\s*\.\s*$/i';

// Heredoc/nowdoc body, double-quoted, single-quoted.
$literalBody = '<<<[ \t]*(["\']?)([A-Za-z_]\w*)\1\r?\n(.*?)\r?\n[ \t]*\2\b'
    . '|"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'';
$literal = '/' . $literalBody . '/s';
$literalAt = '/\G(?:' . $literalBody . ')/s';

// SQL string values inside a literal, blanked to spaces so offsets and line numbers hold.
$sqlValues = ['/\\\\\'.*?\\\\\'/s', '/\\\\".*?\\\\"/s', '/\'[^\']*\'/', '/"[^"]*"/'];

$skipLiteral = function ($source, $i) use ($literalAt) {
    return preg_match($literalAt, $source, $m, 0, $i) ? $i + strlen($m[0]) : null;
};

// End offset of the bracketed span opening at $open, or of the statement starting at $open.
$spanEnd = function ($source, $open, $untilSemicolon) use ($skipLiteral) {
    $depth = 0;
    $length = strlen($source);

    for ($i = $open; $i < $length; $i++) {
        if (($end = $skipLiteral($source, $i)) !== null) {
            $i = $end - 1;
            continue;
        }

        $char = $source[$i];

        if ($char === '(' || $char === '[') {
            $depth++;
        } elseif ($char === ')' || $char === ']') {
            $depth--;

            if (!$untilSemicolon && $depth === 0) {
                return $i;
            }
        } elseif ($untilSemicolon && $char === ';' && $depth <= 0) {
            return $i;
        }
    }

    return $length;
};

/**
 * @return array<int, array{offset: int, ident: string}>
 */
$findUses = function ($source, $start, $end, $isFromRaw) use ($literal, $sqlValues, $qualifiedUse, $keywordUse, $leadingUse, $prefixBefore) {
    $segment = substr($source, $start, $end - $start);
    $uses = [];

    if (!preg_match_all($literal, $segment, $strings, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return $uses;
    }

    foreach ($strings as $index => $string) {
        $body = null;

        foreach ([3, 4, 5] as $group) {
            if (isset($string[$group]) && $string[$group][1] !== -1) {
                [$body, $bodyOffset] = $string[$group];
                break;
            }
        }

        if ($body === null) {
            continue;
        }

        $text = $body;
        foreach ($sqlValues as $pattern) {
            $text = preg_replace_callback($pattern, function ($m) {
                return preg_replace('/[^\n]/', ' ', $m[0]);
            }, $text);
        }

        $hits = [];
        foreach ([$qualifiedUse, $keywordUse] as $pattern) {
            if (preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[1] as $hit) {
                    $hits[$hit[1]] = $hit[0];
                }
            }
        }

        if ($isFromRaw && $index === 0 && preg_match($leadingUse, $text, $m, PREG_OFFSET_CAPTURE)) {
            $hits[$m[1][1]] = $m[1][0];
        }

        foreach ($hits as $offset => $ident) {
            // `$wpdb->prefix . 'fe_x.col'`: the prefix is concatenated directly onto the table.
            $leading = ltrim(substr($text, 0, $offset), '`') === '';
            if ($leading && preg_match($prefixBefore, substr($segment, 0, $string[0][1]))) {
                continue;
            }

            $uses[] = ['offset' => $start + $bodyOffset + $offset, 'ident' => $ident];
        }
    }

    return $uses;
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

        if (!preg_match_all($rawCall, $source, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($calls as $call) {
            $open = $call[0][1] + strlen($call[0][0]) - 1;
            $close = $spanEnd($source, $open, false);
            $isFromRaw = strcasecmp($call[1][0], 'fromRaw') === 0;

            $uses = $findUses($source, $open + 1, $close, $isFromRaw);

            // A variable passed in: check what it was assigned earlier in the same function.
            $functionStart = 0;
            if (preg_match_all('/\bfunction\b/', substr($source, 0, $open), $fn, PREG_OFFSET_CAPTURE)) {
                $functionStart = end($fn[0])[1];
            }

            preg_match_all('/\$([a-zA-Z_]\w*)(?!\s*->)/', substr($source, $open, $close - $open), $vars);

            foreach (array_unique(array_diff($vars[1], ['this', 'wpdb'])) as $var) {
                $scope = substr($source, $functionStart, $open - $functionStart);

                if (!preg_match_all('/\$' . $var . '\s*\.?=(?!=)/', $scope, $assignments, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($assignments[0] as $assignment) {
                    $rhsStart = $functionStart + $assignment[1] + strlen($assignment[0]);
                    $uses = array_merge($uses, $findUses($source, $rhsStart, $spanEnd($source, $rhsStart, true), false));
                }
            }

            foreach ($uses as $use) {
                $line = substr_count($source, "\n", 0, $use['offset']) + 1;
                $key = $path . ':' . $line . ':' . $use['ident'];

                $violations[$key] = [
                    'file'  => str_replace($root . '/', '', $path),
                    'line'  => $line,
                    'ident' => $use['ident'],
                ];
            }
        }
    }
}

echo "raw-sql-prefix: scanned {$scanned} PHP files\n";

if (!$violations) {
    echo "OK - no unprefixed or hardcoded-prefix tables inside raw SQL.\n";
    exit(0);
}

echo "\nFAIL - " . count($violations) . " violation(s):\n\n";

foreach ($violations as $violation) {
    $problem = stripos($violation['ident'], 'wp_') === 0 ? 'hardcoded prefix' : 'unprefixed table';
    echo "  {$violation['file']}:{$violation['line']}  {$problem}: {$violation['ident']}\n";
}

echo "\nRaw SQL bypasses the query grammar. Resolve the prefix explicitly:\n";
echo "  global \$wpdb; \$table = \$wpdb->prefix . 'fe_guests';\n";
echo "  ->selectRaw(\"COUNT(`{\$table}`.`id`) AS total\")\n";

exit(1);
