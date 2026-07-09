<?php
/**
 * Standalone scope runner — invoked by `composer scope` from dev/.
 * Avoids namespace references in composer.json (which the scaffolder
 * corrupts during string replacement).
 */

if (PHP_MAJOR_VERSION < 8 || (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION < 1)) {
    echo "Error: PHP 8.1+ required. Current: " . PHP_VERSION . PHP_EOL;
    echo "Run with: php8.1 composer scope" . PHP_EOL;
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

// Find the ComposerScript class dynamically — works regardless of namespace
$composerScriptFile = __DIR__ . '/../ComposerScript.php';
$content = file_get_contents($composerScriptFile);

preg_match('/^namespace\s+(.+?)\s*;/m', $content, $m);
$namespace = $m[1] ?? '';
$class = $namespace ? $namespace . '\\ComposerScript' : 'ComposerScript';

if (!class_exists($class)) {
    require $composerScriptFile;
}

$vendorDir = realpath(__DIR__ . '/../../vendor');
if (!$vendorDir || !is_dir($vendorDir)) {
    echo "Error: vendor/ not found. Run composer install in plugin root first." . PHP_EOL;
    exit(1);
}

// Read namespace from root composer.json
$rootJson = json_decode(file_get_contents($vendorDir . '/../composer.json'), true);
$pluginNs = $rootJson['extra']['wpfluent']['namespace']['current'] ?? null;

if (!$pluginNs) {
    echo "Error: Plugin namespace not found in composer.json" . PHP_EOL;
    exit(1);
}

// Call the scoping methods directly
$class::scopeVendorPackagesStandalone($vendorDir, $pluginNs);

shell_exec('cd ' . escapeshellarg(dirname($vendorDir)) . ' && composer dump-autoload');

echo PHP_EOL . "Done. Vendor packages scoped under {$pluginNs}\\Vendor\\" . PHP_EOL;
