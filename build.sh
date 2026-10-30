#!/usr/bin/env bash
#
# Build an installable FluentEvent zip into builds/.
#
#   ./build.sh                 zip the assets/ already built
#   ./build.sh --node-build    run `npm run build` first
#
# Not `./wpf app:build`: its built-in excludes drop composer.json, which the
# framework reads on every boot (devtools v1.0.48).
#
# The zip is assembled from an explicit list of paths, so a new top-level file
# reaches users only once it is added to PAYLOAD.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SLUG="fluent-event"
BUILD_DIR="$ROOT/builds"
STAGE="$BUILD_DIR/$SLUG"

# resources/, package.json and vite.config.js are the source of assets/. WordPress.org
# requires the human-readable source of compiled code, and this repo is not public.
PAYLOAD=(fluent-event.php readme.txt composer.json app assets boot config database language Modules resources package.json vite.config.js)
VENDOR_PACKAGES=(autoload.php composer wpfluent woocommerce)

die() { echo "Error: $*" >&2; exit 1; }

nodeBuild=false
for arg in "$@"; do
    case "$arg" in
        --node-build) nodeBuild=true ;;
        *) die "Unknown option: $arg" ;;
    esac
done

cd "$ROOT"
command -v composer >/dev/null || die "composer not found."
command -v zip >/dev/null || die "zip not found."
mkdir -p "$BUILD_DIR"

if "$nodeBuild"; then
    # npm run build flips config/app.php to production and empties assets/;
    # put the working tree back so the local site stays on the dev server.
    cp config/app.php "$BUILD_DIR/app.php.orig"
    buildOk=true
    npm run build || buildOk=false
    mv "$BUILD_DIR/app.php.orig" config/app.php
    mkdir -p assets
    [ -f assets/index.php ] || printf '<?php\n\n// Silence is golden' > assets/index.php
    "$buildOk" || die "npm run build failed."
fi

compgen -G "assets/*.js" >/dev/null || die "assets/ has no built files. Run ./build.sh --node-build."

rm -rf "$STAGE"
mkdir -p "$STAGE/vendor"

for item in "${PAYLOAD[@]}"; do
    [ -e "$item" ] || die "$item is missing."
    cp -R "$item" "$STAGE/"
done

# Production packages only; a dev `composer install` can leave test tooling in vendor/.
for pkg in "${VENDOR_PACKAGES[@]}"; do
    [ -e "vendor/$pkg" ] || die "vendor/$pkg is missing. Run composer install."
    cp -R "vendor/$pkg" "$STAGE/vendor/"
done

# Anything but production loads assets from a Vite dev server the target site doesn't have.
sed -E "s/'env'[[:space:]]*=>[[:space:]]*'[a-z]+'/'env' => 'production'/" "$STAGE/config/app.php" > "$STAGE/config/app.php.tmp"
mv "$STAGE/config/app.php.tmp" "$STAGE/config/app.php"
grep -qE "'env' => 'production'" "$STAGE/config/app.php" || die "Could not set env to production in config/app.php."

# FluentEvent\Dev\ maps dev/ and the scripts call Dev\ComposerScript; neither ships.
php -r '
$file = $argv[1];
$json = json_decode(file_get_contents($file), true);
if (!is_array($json)) { fwrite(STDERR, "composer.json did not parse\n"); exit(1); }
unset($json["autoload"]["psr-4"]["FluentEvent\\Dev\\"], $json["scripts"]);
file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$STAGE/composer.json" || die "Could not strip dev keys from composer.json."

# Dumped in the copy so the working tree keeps its dev autoloader.
composer dump-autoload --working-dir="$STAGE" --classmap-authoritative --no-dev --quiet \
    || die "composer dump-autoload failed."

if grep -q "FluentEvent..Dev" "$STAGE/vendor/composer/autoload_psr4.php"; then
    die "The Dev namespace is still in the shipped autoloader."
fi

# Under --classmap-authoritative an entry pointing at a missing file is a fatal, not a miss.
php -r '
$map = require $argv[1];
$missing = [];
foreach ($map as $class => $file) {
    if (!file_exists($file)) { $missing[] = $class; }
}
if ($missing) {
    fwrite(STDERR, "Classmap points at missing files: " . implode(", ", array_slice($missing, 0, 5)) . "\n");
    exit(1);
}
echo count($map) . " classes mapped, all present\n";
' "$STAGE/vendor/composer/autoload_classmap.php" || die "Shipped classmap is broken."

VERSION="$(grep -m1 -E '^[[:space:]]*\*?[[:space:]]*Version:' "$STAGE/fluent-event.php" | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r ')"
[ -n "$VERSION" ] || die "Could not read Version from fluent-event.php."

ZIP="$BUILD_DIR/$SLUG-$VERSION.zip"
rm -f "$ZIP"
(cd "$BUILD_DIR" && zip -qrX "$ZIP" "$SLUG" -x '*.DS_Store')

echo "Built $ZIP"
