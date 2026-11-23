#!/usr/bin/env bash
#
# Build an installable FluentEvent zip into builds/.
#
#   ./build.sh                 zip the assets/ already built
#   ./build.sh --node-build    run `npm run build` first
#   ./build.sh --pot           regenerate language/fluent-event.pot first (needs WP-CLI)
#   ./build.sh --wporg         the WordPress.org zip: no self-hosted updater (-wporg.zip)
#
# Not `./wpf app:build`: its built-in excludes drop composer.json, which the
# framework reads on every boot (devtools v1.0.48).
#
# The zip is assembled from an explicit list of paths, so a new top-level file
# reaches users only once it is added to PAYLOAD. A root entry on neither
# PAYLOAD nor NOT_SHIPPED is named at build time.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SLUG="fluent-event"
BUILD_DIR="$ROOT/builds"
STAGE_ROOT="$BUILD_DIR/.stage"
STAGE="$STAGE_ROOT/$SLUG"

# Only what runs on a site ships. The source of assets/ (resources/, package.json,
# package-lock.json, vite.config.js) stays in the public GitHub repository; the readme's
# "Source code" section links it for WordPress.org reviewers.
PAYLOAD=(fluent-event.php readme.txt composer.json app assets boot config database language Modules)
VENDOR_PACKAGES=(autoload.php composer wpfluent woocommerce)

# Root entries left out on purpose. Only silences the "not shipped" report below.
NOT_SHIPPED=(
    .agent .claude .distignore .DS_Store .git .gitattributes .gitignore .playwright-mcp .vscode
    build.sh builds CLAUDE.md composer.lock dev dev-docs-repo docs node_modules
    package.json package-lock.json phpcs.xml resources vite.config.js
    phpunit.integration.xml phpunit.xml.dist PARITY-GAPS.md plugin-check README.md release-note test tests vendor
    vitest.config.mjs wpf .gitmodules
)

# VCS files, editor backups and OS metadata that cp carries into the stage
# (vendor/wpfluent/framework/.gitignore shipped before this sweep).
# Database dumps, logs and backups too: a web server serves them to anyone, so WordPress.org
# flags them. vendor/wpfluent/framework/database/levenshtein.sql is one; nothing loads it.
JUNK=(".DS_Store" "__MACOSX" "._*" "Thumbs.db" "*~" "*.orig" "*.rej" ".gitignore" ".gitattributes" ".gitkeep" "*.map"
    "*.sql" "*.sqlite" "*.log" "*.bak" "*.old" "*.swp" ".env" ".env.*")

die() { echo "Error: $*" >&2; exit 1; }

nodeBuild=false
makePot=false
wporg=false
for arg in "$@"; do
    case "$arg" in
        --node-build) nodeBuild=true ;;
        --pot) makePot=true ;;
        --wporg) wporg=true ;;
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

if "$makePot"; then
    # Written to the working tree, so the .pot is committed with the release that ships it.
    # The Vue and block strings reach it through their PHP maps (config/i18n.php,
    # PortalHandler::strings(), BlockEditorHandler::strings()); assets/ holds built copies only.
    command -v wp >/dev/null || die "wp (WP-CLI) not found."
    wp i18n make-pot . "language/$SLUG.pot" --slug="$SLUG" --domain="$SLUG" \
        --exclude=assets,builds,dev,dev-docs-repo,tests,test,plugin-check,node_modules,vendor,.claude,.agent,.playwright-mcp \
        || die "wp i18n make-pot failed."
fi

# Production enqueues every script and stylesheet through assets/manifest.json, which
# Vite writes last. A missing manifest or a file it names means a blank screen.
php -r '
$map = json_decode((string) @file_get_contents($argv[1]), true);
if (!is_array($map) || !$map) { fwrite(STDERR, "assets/manifest.json is missing or empty.\n"); exit(1); }
$missing = [];
foreach ($map as $record) {
    foreach (array_merge([$record["file"] ?? ""], $record["css"] ?? []) as $file) {
        if ($file === "" || !file_exists(dirname($argv[1]) . "/" . $file)) { $missing[] = $file; }
    }
}
if ($missing) { fwrite(STDERR, "The manifest names missing files: " . implode(", ", array_slice($missing, 0, 5)) . "\n"); exit(1); }
' "$ROOT/assets/manifest.json" || die "assets/ is not a complete build. Run ./build.sh --node-build."

# On an RTL site the enqueuer loads foo.rtl.css in place of every foo.css without checking it
# exists, so a missing or stale twin is a 404 or an unmirrored page on Arabic and Hebrew sites.
command -v node >/dev/null || die "node not found."
node resources/vite/rtl-css.js --check || die "RTL stylesheets are missing or stale. Run ./build.sh --node-build."

# A whitelist fails closed, but quietly: say what is left out and not known to be.
UNKNOWN=()
for entry in * .[!.]*; do
    [ -e "$entry" ] || continue
    printf '%s\n' "${PAYLOAD[@]}" "${NOT_SHIPPED[@]}" | grep -qxF "$entry" && continue
    UNKNOWN+=("$entry")
done
if [ ${#UNKNOWN[@]} -gt 0 ]; then
    echo "Not shipped, and not on PAYLOAD or NOT_SHIPPED:"
    printf '    %s\n' "${UNKNOWN[@]}"
fi

rm -rf "$STAGE_ROOT"
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

# WordPress.org delivers its own updates and does not allow a plugin to update from another
# server, so that zip leaves the updater out. boot/app.php skips it when the class is absent.
# Removed before the dump, so the classmap never names it.
UPDATER_DIR="app/Services/PluginManager"
if "$wporg"; then
    rm -rf "$STAGE/$UPDATER_DIR"
fi

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

for pattern in "${JUNK[@]}"; do
    find "$STAGE" -name "$pattern" -exec rm -rf {} + 2>/dev/null || true
done
# Bundled libraries keep their license files; their own readmes and changelogs are not needed.
find "$STAGE/vendor" -type f \( -iname "*.md" -o -iname "changelog*" \) -delete
# macOS extended attributes become ._ files inside the archive on some unzip tools.
if command -v xattr >/dev/null; then
    xattr -cr "$STAGE" 2>/dev/null || true
fi

# Without an index.php, a server with directory listing on shows the folder's files.
# After the junk sweep so nothing removes them; existing files are kept.
ADDED=0
while IFS= read -r -d '' dir; do
    if [ ! -e "$dir/index.php" ]; then
        printf '<?php\n\n// Silence is golden\n' > "$dir/index.php"
        ADDED=$((ADDED + 1))
    fi
done < <(find "$STAGE" -type d -print0)
echo "$ADDED index.php files added"

if "$wporg"; then
    # Nothing that checks or fetches updates from our server may reach the WordPress.org zip.
    if grep -rqE "fluentapi\.wpmanageninja\.com|pre_set_site_transient_update_plugins|upgrader_pre_download" "$STAGE" \
        --include='*.php' --exclude-dir=woocommerce; then
        die "The WordPress.org build still contains self-hosted update code."
    fi
    ZIP="$BUILD_DIR/$SLUG-$VERSION-wporg.zip"
else
    [ -f "$STAGE/$UPDATER_DIR/PluginUpdater.php" ] || die "The updater is missing from the build."
    ZIP="$BUILD_DIR/$SLUG-$VERSION.zip"
fi
rm -f "$ZIP"
(cd "$STAGE_ROOT" && zip -qrX "$ZIP" "$SLUG")
rm -rf "$STAGE_ROOT"

# Source, build setup, dev code and tests never ship. Checked on the archive itself, so a
# PAYLOAD edit cannot slip one in. Top level only: vendor/wpfluent/framework has its own src/.
# Listed into a variable first: under pipefail, grep -q exiting early makes unzip die of SIGPIPE,
# which fails the pipeline and skips this check.
LISTING="$(unzip -Z1 "$ZIP")" || { rm -f "$ZIP"; die "Could not list $ZIP."; }
if grep -qE "^$SLUG/((resources|src|dev|node_modules|tests?)/|package(-lock)?\.json$|vite\.config\.js$)" <<<"$LISTING"; then
    rm -f "$ZIP"
    die "The zip contains source or dev files that must not ship."
fi
# Checked on the archive too, so dropping a pattern from JUNK cannot ship one of these again.
if grep -qiE "\.(sql|sqlite|log|bak|old|swp)$|/\.env(\..*)?$" <<<"$LISTING"; then
    rm -f "$ZIP"
    die "The zip contains a database dump, log, backup or .env file."
fi

echo "Built $ZIP"
echo "  size    $(du -h "$ZIP" | cut -f1 | tr -d '[:space:]')"
echo "  files   $(unzip -Z1 "$ZIP" | grep -vc '/$')"
echo "  sha256  $(shasum -a 256 "$ZIP" | cut -d' ' -f1)"
