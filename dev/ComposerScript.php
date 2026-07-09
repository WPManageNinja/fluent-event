<?php

namespace FluentEvents\Dev;

use Exception;
use Composer\Script\Event;
use InvalidArgumentException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Composer script to automatically update namespaces and autoload
 * entries for WPFluent packages after Composer install/update,
 * and isolate third-party vendor packages using PHP-Scoper.
 */
class ComposerScript
{
    /**
     * List of packages to exclude from namespace updates.
     *
     * @var array
     */
    private static $excludables = [];

    /**
     * Path to the JSON file used for tracking updated files.
     *
     * @var string
     */
    private static $composerFiles = __DIR__ . '/wpf.json';

    /**
     * Main entry point triggered by Composer post-install/post-update.
     *
     * @param Event $event Composer event instance.
     * @return void
     */
    public static function run(Event $event)
    {
        static::execute($event);

        shell_exec('composer dump-autoload');
    }

    /**
     * Public entry point for standalone scoping (called by tools/scope-runner.php
     * and the `./wpf scope` CLI command).
     *
     * @param string $vendorDir Path to the vendor directory.
     * @param string $namespace Plugin namespace.
     * @return void
     */
    public static function scopeVendorPackagesStandalone($vendorDir, $namespace)
    {
        static::scopeVendorPackages($vendorDir, $namespace);
    }

    /**
     * Executes the full namespace update process.
     *
     * @param Event $event Composer event instance.
     * @return void
     */
    private static function execute($event)
    {
        echo "Please wait, this may take a while." . PHP_EOL;

        static::resetReportFile();

        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');

        $namespace = static::getNamespaceFromRootComposer($vendorDir);

        // Step 1: Rewrite WPFluent framework namespaces (simple string replace)
        static::updateNamespaceInWPFluent($vendorDir, $namespace);

        // Step 2: Process WPFluent-specific packages only
        static::processVendorPackages($vendorDir, $namespace, $event);

        static::updateAutoloadFileEntries($vendorDir, $namespace);

        // Step 3: Scope non-WPFluent vendor packages with PHP-Scoper
        static::scopeVendorPackages($vendorDir, $namespace);
    }

    // =========================================================================
    // Vendor Namespace Isolation (PHP-Scoper)
    // =========================================================================

    /**
     * Run PHP-Scoper to isolate non-WPFluent vendor package namespaces.
     * Requires PHP 8.1+. Skips gracefully if not available.
     *
     * @param string $vendorDir Path to the vendor directory.
     * @param string $namespace Plugin namespace.
     * @return void
     */
    private static function scopeVendorPackages($vendorDir, $namespace)
    {
        $pluginRoot = dirname($vendorDir);
        $scoperPhar = $pluginRoot . '/dev/tools/php-scoper.phar';
        $scoperConfig = $pluginRoot . '/dev/tools/scoper.inc.php';

        if (!file_exists($scoperPhar)) {
            echo "  [skip] php-scoper.phar not found in dev/tools/" . PHP_EOL;
            return;
        }

        if (!file_exists($scoperConfig)) {
            echo "  [skip] scoper.inc.php not found in dev/tools/" . PHP_EOL;
            return;
        }

        // Check if there are any non-wpfluent packages to scope
        $hasGenericPackages = false;
        foreach (glob($vendorDir . '/*/*', GLOB_ONLYDIR) as $dir) {
            $rel = str_replace($vendorDir . '/', '', $dir);
            if (!str_starts_with($rel, 'wpfluent/') &&
                !str_starts_with($rel, 'composer/') &&
                !str_starts_with($rel, 'bin/')) {
                $hasGenericPackages = true;
                break;
            }
        }

        if (!$hasGenericPackages) {
            echo "  [skip] No third-party packages to scope." . PHP_EOL;
            return;
        }

        // PHP-Scoper requires PHP 8.1+. Check before starting.
        $php = static::findPhp8Binary();
        if (!$php) {
            echo PHP_EOL;
            echo "  ⚠  Vendor namespace isolation skipped — PHP 8.1+ required." . PHP_EOL;
            echo "     Current PHP: " . PHP_VERSION . " (" . PHP_BINARY . ")" . PHP_EOL;
            echo PHP_EOL;
            echo "     To scope vendor packages, run:" . PHP_EOL;
            echo "     $ ./wpf scope" . PHP_EOL;
            echo "     or: cd dev && composer scope  (with PHP 8.1+)" . PHP_EOL;
            echo PHP_EOL;
            echo "     Your plugin will work without scoping, but third-party" . PHP_EOL;
            echo "     packages won't be namespace-isolated." . PHP_EOL;
            echo PHP_EOL;
            return;
        }

        echo "  Scoping third-party vendor packages..." . PHP_EOL;

        $tempDir = sys_get_temp_dir() . '/wpf-scoped-' . md5($pluginRoot);

        // Clean previous run
        if (is_dir($tempDir)) {
            static::removeDir($tempDir);
        }

        // Determine the minimum PHP version for the parser.
        // Must match the highest PHP required by any dependency so the
        // parser can handle the syntax used in vendor code.
        $installedPkgs = static::readJson($vendorDir . '/composer/installed.json');
        $minPhp = '7.4';
        foreach ($installedPkgs['packages'] ?? [] as $pkg) {
            $req = $pkg['require']['php'] ?? '';
            if (preg_match('/(\d+\.\d+)/', $req, $m)) {
                if (version_compare($m[1], $minPhp, '>')) {
                    $minPhp = $m[1];
                }
            }
        }

        $cmd = sprintf(
            '%s -d pcre.jit=0 %s add-prefix --config=%s --output-dir=%s --php-version=%s --force --no-interaction -- %s 2>&1',
            escapeshellarg($php),
            escapeshellarg($scoperPhar),
            escapeshellarg($scoperConfig),
            escapeshellarg($tempDir),
            escapeshellarg($minPhp),
            escapeshellarg($vendorDir)
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            echo "  [error] PHP-Scoper failed (exit $exitCode):" . PHP_EOL;
            echo "  " . implode("\n  ", array_slice($output, -5)) . PHP_EOL;
            return;
        }

        // Replace vendor files with scoped output.
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($tempDir) + 1);

            // Skip WPFluent packages — already namespace-rewritten by earlier step
            if (str_starts_with($relativePath, 'wpfluent/')) {
                continue;
            }

            $targetPath = $vendorDir . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $targetPath);
                if (pathinfo($relativePath, PATHINFO_EXTENSION) === 'php') {
                    $count++;
                }
            }
        }

        // Clean up temp directory
        static::removeDir($tempDir);

        // Update PSR-4 autoload entries in installed.json
        $prefix = $namespace . '\\Vendor\\';
        $installedFile = $vendorDir . '/composer/installed.json';
        $installed = static::readJson($installedFile);

        if (!empty($installed['packages'])) {
            foreach ($installed['packages'] as &$pkg) {
                if (str_contains($pkg['name'], 'wpfluent')) {
                    continue;
                }

                if (!empty($pkg['autoload']['psr-4'])) {
                    $newPsr4 = [];
                    foreach ($pkg['autoload']['psr-4'] as $ns => $path) {
                        $newPsr4[str_starts_with($ns, $prefix) ? $ns : $prefix . $ns] = $path;
                    }
                    $pkg['autoload']['psr-4'] = $newPsr4;
                }

                if (!empty($pkg['autoload']['psr-0'])) {
                    $newPsr0 = [];
                    foreach ($pkg['autoload']['psr-0'] as $ns => $path) {
                        $newPsr0[str_starts_with($ns, $prefix) ? $ns : $prefix . $ns] = $path;
                    }
                    $pkg['autoload']['psr-0'] = $newPsr0;
                }

                $pkgComposer = $vendorDir . '/' . $pkg['name'] . '/composer.json';
                if (file_exists($pkgComposer)) {
                    $pkgJson = static::readJson($pkgComposer);
                    if (!empty($pkgJson['autoload']['psr-4'])) {
                        $newPsr4 = [];
                        foreach ($pkgJson['autoload']['psr-4'] as $ns => $path) {
                            $newPsr4[str_starts_with($ns, $prefix) ? $ns : $prefix . $ns] = $path;
                        }
                        $pkgJson['autoload']['psr-4'] = $newPsr4;
                        static::writeJson($pkgComposer, $pkgJson);
                    }
                }
            }
            static::writeJson($installedFile, $installed);
        }

        // Fix polyfill function calls that PHP-Scoper left unqualified
        static::qualifyNonInternalCalls($vendorDir, $namespace);

        // Generate report of edge cases the tooling couldn't handle
        static::reportUnscopedReferences($vendorDir, $namespace);

        echo "  Scoped $count PHP files under {$namespace}\\Vendor\\" . PHP_EOL;
    }

    /**
     * Qualify unqualified calls to polyfill/helper functions that PHP-Scoper's
     * stubs incorrectly treated as PHP internals.
     */
    private static function qualifyNonInternalCalls($vendorDir, $namespace)
    {
        $prefix = $namespace . '\\Vendor';

        // Discover functions defined in the scoped root namespace
        $scopedFunctions = [];
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($vendorDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iter as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') continue;
            if (str_contains($file->getPathname(), '/wpfluent/')) continue;
            if (str_contains($file->getPathname(), '/composer/')) continue;

            $content = file_get_contents($file->getPathname());
            if (!preg_match('/^namespace\s+(' . preg_quote($prefix, '/') . ')\s*;/m', $content)) continue;

            preg_match_all('/^\s*function\s+([a-zA-Z_]\w*)\s*\(/m', $content, $fns);
            foreach ($fns[1] as $fn) {
                $scopedFunctions[$fn] = $prefix . '\\' . $fn;
            }
        }

        if (empty($scopedFunctions)) return;

        // Keep only functions that are NOT native PHP internals
        foreach ($scopedFunctions as $fn => $fqn) {
            try {
                $ref = new \ReflectionFunction($fn);
                if ($ref->isInternal()) {
                    unset($scopedFunctions[$fn]);
                }
            } catch (\ReflectionException $e) {
                // Not a built-in — keep for patching
            }
        }

        if (empty($scopedFunctions)) return;

        // Qualify unqualified calls in scoped vendor files
        $fnNames = array_keys($scopedFunctions);
        usort($fnNames, fn($a, $b) => strlen($b) - strlen($a));
        $alternation = implode('|', array_map(fn($fn) => preg_quote($fn, '/'), $fnNames));
        $pattern = '/(?<![\\\\$>\w:])(?<!function )\b(' . $alternation . ')\s*\(/';

        $patched = 0;
        $phpIter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($vendorDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($phpIter as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') continue;
            if (str_contains($file->getPathname(), '/wpfluent/')) continue;
            if (str_contains($file->getPathname(), '/composer/')) continue;

            $content = file_get_contents($file->getPathname());
            if (!str_contains($content, $prefix)) continue;

            $replaced = preg_replace_callback($pattern, function ($m) use ($scopedFunctions) {
                return isset($scopedFunctions[$m[1]])
                    ? '\\' . $scopedFunctions[$m[1]] . '('
                    : $m[0];
            }, $content);

            if ($replaced !== $content) {
                file_put_contents($file->getPathname(), $replaced);
                $patched++;
            }
        }

        echo "  Qualified " . count($scopedFunctions) . " non-internal functions in $patched files." . PHP_EOL;
    }

    /**
     * Scan scoped vendor code for potential unscoped namespace references
     * that PHP-Scoper couldn't rewrite. Writes dev/scoper-report.txt.
     */
    private static function reportUnscopedReferences($vendorDir, $namespace)
    {
        $prefix = $namespace . '\\Vendor';

        $installedFile = $vendorDir . '/composer/installed.json';
        $installed = static::readJson($installedFile);
        $originalNamespaces = [];

        foreach ($installed['packages'] ?? [] as $pkg) {
            if (str_contains($pkg['name'], 'wpfluent')) continue;
            foreach (['psr-4', 'psr-0'] as $type) {
                foreach ($pkg['autoload'][$type] ?? [] as $ns => $path) {
                    $original = str_replace($prefix . '\\', '', $ns);
                    if ($original && $original !== $ns) {
                        $originalNamespaces[] = rtrim($original, '\\');
                    }
                }
            }
        }

        if (empty($originalNamespaces)) return;

        $findings = [];
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($vendorDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iter as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') continue;
            if (str_contains($file->getPathname(), '/wpfluent/')) continue;
            if (str_contains($file->getPathname(), '/composer/')) continue;

            $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
            if (!$lines) continue;

            $relativePath = str_replace($vendorDir . '/', '', $file->getPathname());

            foreach ($lines as $lineNum => $line) {
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                    continue;
                }

                // String concatenation building class names (skip date formats)
                if ((preg_match('/\.\s*[\'"][\\\\]+[A-Z]/', $line) ||
                    preg_match('/[\'"][A-Z][a-zA-Z]+\\\\[\'"][\s]*\./', $line)) &&
                    !preg_match('/format|rawFormat|date/', $line)) {
                    $findings[] = [
                        'file' => $relativePath, 'line' => $lineNum + 1,
                        'type' => 'dynamic-class-name', 'code' => trim($line),
                    ];
                    continue;
                }

                // Unscoped namespace in string literals
                foreach ($originalNamespaces as $ns) {
                    $escaped = str_replace('\\', '\\\\', $ns);
                    if (preg_match('/[\'"]' . preg_quote($escaped, '/') . '\\\\/', $line)) {
                        if (!str_contains($line, $prefix)) {
                            $findings[] = [
                                'file' => $relativePath, 'line' => $lineNum + 1,
                                'type' => 'unscoped-string-reference',
                                'code' => trim($line), 'namespace' => $ns,
                            ];
                        }
                    }
                }

                // Variable assigned from unscoped string used as class
                foreach ($originalNamespaces as $ns) {
                    $escaped = str_replace('\\', '\\\\', $ns);
                    if (preg_match('/\$\w+\s*=\s*[\'"]' . preg_quote($escaped, '/') . '\\\\/', $line)) {
                        if (!str_contains($line, $prefix)) {
                            $findings[] = [
                                'file' => $relativePath, 'line' => $lineNum + 1,
                                'type' => 'dynamic-class-from-string',
                                'code' => trim($line), 'namespace' => $ns,
                            ];
                        }
                    }
                }
            }
        }

        $pluginRoot = dirname($vendorDir);
        $reportFile = $pluginRoot . '/dev/scoper-report.txt';

        if (empty($findings)) {
            $report = "Scoper Report — " . date('Y-m-d H:i:s') . "\n";
            $report .= "Status: CLEAN — No unscoped references detected.\n";
            $report .= "Prefix: {$prefix}\n";
            file_put_contents($reportFile, $report);
            echo "  Report: No unscoped references found." . PHP_EOL;
            return;
        }

        $report = "Scoper Report — " . date('Y-m-d H:i:s') . "\n";
        $report .= "Status: " . count($findings) . " potential unscoped reference(s) found.\n";
        $report .= "Prefix: {$prefix}\n";
        $report .= str_repeat('-', 70) . "\n\n";
        $report .= "These are patterns that PHP-Scoper could not rewrite automatically.\n";
        $report .= "Review each one — most are harmless, but some may need manual fixes.\n\n";

        foreach ($findings as $i => $f) {
            $report .= ($i + 1) . ". [{$f['type']}] {$f['file']}:{$f['line']}\n";
            $report .= "   {$f['code']}\n";
            if (isset($f['namespace'])) {
                $report .= "   Original namespace: {$f['namespace']}\n";
            }
            $report .= "\n";
        }

        file_put_contents($reportFile, $report);
        echo "  Report: " . count($findings) . " potential issue(s) → dev/scoper-report.txt" . PHP_EOL;
    }

    // =========================================================================
    // PHP Binary Discovery
    // =========================================================================

    /**
     * Find a PHP 8.1+ binary for running PHP-Scoper.
     *
     * @return string|null Path to PHP 8.1+ binary, or null if not found.
     */
    private static function findPhp8Binary()
    {
        if (PHP_MAJOR_VERSION > 8 || (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION >= 1)) {
            return PHP_BINARY;
        }

        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? '');
        if ($home) {
            $managed = glob($home . '/.fluent-studio/php/*/php');
            if ($managed) {
                usort($managed, function ($a, $b) {
                    return version_compare(basename(dirname($b)), basename(dirname($a)));
                });
                foreach ($managed as $bin) {
                    $parts = explode('.', basename(dirname($bin)));
                    $major = (int)($parts[0] ?? 0);
                    $minor = (int)($parts[1] ?? 0);
                    if ($major > 8 || ($major === 8 && $minor >= 1)) {
                        return $bin;
                    }
                }
            }
        }

        $candidates = [
            '/opt/homebrew/bin/php',
            '/usr/local/bin/php',
            "$home/Library/Application Support/Herd/bin/php",
        ];
        foreach ($candidates as $bin) {
            if (!file_exists($bin)) continue;
            $out = shell_exec(escapeshellarg($bin) . ' -r "echo PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION;" 2>/dev/null');
            if ($out) {
                $parts = explode('.', trim($out));
                if (((int)$parts[0] > 8) || ((int)$parts[0] === 8 && (int)($parts[1] ?? 0) >= 1)) {
                    return $bin;
                }
            }
        }

        return null;
    }

    // =========================================================================
    // WPFluent Framework Namespace Rewriting (existing, untouched)
    // =========================================================================

    private static function resetReportFile()
    {
        file_put_contents(static::$composerFiles, '');
    }

    private static function getNamespaceFromRootComposer($vendorDir)
    {
        $composerJson = $vendorDir . '/../composer.json';

        if (!file_exists($composerJson)) {
            throw new InvalidArgumentException("composer.json not found.");
        }

        $data = json_decode(file_get_contents($composerJson));
        $namespace = $data->extra->wpfluent->namespace->current ?? null;

        if (!$namespace) {
            throw new InvalidArgumentException("Namespace not set in composer.json.");
        }

        $excludes = [];
        if (isset($data->extra->wpfluent->excludes)) {
            $excludes = (array) $data->extra->wpfluent->excludes;
        }

        static::$excludables = array_unique(array_merge(
            static::$excludables, $excludes
        ));

        return $namespace;
    }

    private static function updateNamespaceInWPFluent($vendorDir, $namespace)
    {
        $itr = static::getFilesIterator(
            $vendorDir . '/wpfluent/framework/src/'
        );

        foreach ($itr as $file) {
            if ($file->isDir()) continue;

            $updated = str_replace(
                ['WPFluent\\', 'WPFluentPackage\\'],
                [$namespace . '\\Framework\\', $namespace . '\\'],
                file_get_contents($file->getPathname())
            );

            file_put_contents($file->getPathname(), $updated);
        }
    }

    private static function processVendorPackages($vendorDir, $namespace, $event)
    {
        $installedFile = $vendorDir . '/composer/installed.json';
        $installedData = static::readJson($installedFile);

        foreach ($installedData['packages'] as &$package) {
            try {
                if ($package['name'] === 'wpfluent/framework') {
                    $package['autoload']['psr-4'] = [
                        "{$namespace}\\Framework\\" => "src/WPFluent"
                    ];

                    $wpfComposer = $vendorDir . '/wpfluent/framework/composer.json';
                    $json = static::readJson($wpfComposer);
                    $json['autoload']['psr-4'] = [
                        "{$namespace}\\Framework\\" => "src/WPFluent"
                    ];
                    static::writeJson($wpfComposer, $json);
                    continue;
                }

                if (str_contains($package['name'], 'wpfluent')) {
                    static::processWPFluentPackage(
                        $package, $vendorDir, $namespace
                    );
                }
                // Generic packages are handled by scopeVendorPackages() via PHP-Scoper
            } catch (Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        }

        static::writeJson($installedFile, $installedData);
    }

    private static function processWPFluentPackage(&$package, $vendorDir, $namespace)
    {
        foreach (static::getFilesIterator($vendorDir . "/{$package['name']}/src/") as $item) {
            if ($item->isDir()) continue;

            $updated = str_replace(
                ['Test\\', 'WPFluent\\', 'WPFluentPackage\\', 'WpfluentPackage\\'],
                [$namespace . '\\Framework\\', $namespace . '\\Framework\\', $namespace . '\\', $namespace . '\\'],
                file_get_contents($item->getPathname())
            );

            file_put_contents($item->getPathname(), $updated);
        }

        $psr4Key = str_replace(
            ['WPFluentPackage', 'WpfluentPackage'],
            $namespace,
            array_key_first($package['autoload']['psr-4'])
        );

        $package['autoload']['psr-4'] = [$psr4Key => "src/"];

        $packageJsonFile = $vendorDir . '/' . $package['name'] . '/composer.json';
        $packageComposerJson = static::readJson($packageJsonFile);
        $packageComposerJson['autoload']['psr-4'] = [$psr4Key => "src/"];
        static::writeJson($packageJsonFile, $packageComposerJson);
    }

    private static function updateAutoloadFileEntries($vendorDir, $namespace)
    {
        $reportData = static::readJson(static::$composerFiles);
        $installedFile = $vendorDir . '/composer/installed.json';
        $installedJson = static::readJson($installedFile);

        if (!$reportData) return;

        foreach ($reportData as $composerFile => $file) {
            $newName = static::renameFile($file['path'], $namespace);
            $composer = static::readJson($composerFile);

            if (isset($composer['autoload']['files'])) {
                foreach ($composer['autoload']['files'] as $key => $fileName) {
                    if ($file['name'] !== $fileName) continue;
                    $composer['autoload']['files'][$key] = static::replaceFileName($file['name'], $newName);
                    foreach ($installedJson['packages'] as &$packageData) {
                        if ($packageData['name'] === $file['package']) {
                            $packageData['autoload']['files'] = $composer['autoload']['files'];
                        }
                    }
                }
            }

            static::writeJson($composerFile, $composer);
            static::writeJson($installedFile, $installedJson);
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private static function getFilesIterator($directory)
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException(
                "The directory '{$directory}' does not exist."
            );
        }

        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory, RecursiveDirectoryIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );
    }

    private static function readJson($path)
    {
        if (!is_file($path)) return [];
        $json = file_get_contents($path);
        if ($json === false) return [];
        $data = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : [];
    }

    private static function writeJson($path, $data)
    {
        file_put_contents($path, json_encode(
            $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ));
    }

    private static function renameFile($path, $namespace)
    {
        $nameParts = explode('/', $path);
        $name = array_pop($nameParts);
        if (str_starts_with($name, $namespace)) return $path;
        $dirPath = implode('/', $nameParts);
        $newName = $dirPath . '/' . $namespace . '-' . md5($path) . '-' . $name;
        return rename($path, $newName) ? $newName : $path;
    }

    private static function replaceFileName($oldName, $newName)
    {
        $parts = explode(DIRECTORY_SEPARATOR, $oldName);
        if (count($parts) > 1) {
            array_pop($parts);
            return rtrim(implode(DIRECTORY_SEPARATOR, $parts), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . pathinfo($newName, PATHINFO_BASENAME);
        }
        return pathinfo($newName, PATHINFO_BASENAME);
    }

    private static function removeDir($dir)
    {
        if (!is_dir($dir)) return;
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
