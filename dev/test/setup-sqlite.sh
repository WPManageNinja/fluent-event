#!/usr/bin/env bash

# Sets up the SQLite test environment.
# Requires that ./wpf init (MySQL init) has already been run,
# since this reuses the WP core and test lib includes from that setup.

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")

WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_TESTS_SQLITE_DIR=${WP_TESTS_SQLITE_DIR-$TMPDIR/wordpress-tests-lib-sqlite}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}
WP_SQLITE_CONTENT_DIR=${WP_SQLITE_CONTENT_DIR-$TMPDIR/wordpress-sqlite-content}

SQLITE_PLUGIN_URL="https://github.com/WordPress/sqlite-database-integration/archive/refs/heads/main.zip"

if [ ! -d "$WP_TESTS_DIR/includes" ]; then
	echo "WP test suite not found at $WP_TESTS_DIR/includes"
	echo "Please run ./wpf init first to set up the MySQL test environment."
	exit 1
fi

if [ ! -d "$WP_CORE_DIR" ]; then
	echo "WordPress core not found at $WP_CORE_DIR"
	echo "Please run ./wpf init first."
	exit 1
fi

set -ex

install_sqlite_content() {

	mkdir -p "$WP_SQLITE_CONTENT_DIR/plugins"
	mkdir -p "$WP_SQLITE_CONTENT_DIR/database"

	SQLITE_PLUGIN_DIR="$WP_SQLITE_CONTENT_DIR/plugins/sqlite-database-integration"

	if [ ! -d "$SQLITE_PLUGIN_DIR" ] || [ ! -f "$SQLITE_PLUGIN_DIR/db.copy" ]; then
		echo "Downloading WordPress SQLite Database Integration plugin..."
		rm -rf "$SQLITE_PLUGIN_DIR"
		curl -sL "$SQLITE_PLUGIN_URL" -o "$TMPDIR/sqlite-plugin.zip"
		unzip -q "$TMPDIR/sqlite-plugin.zip" -d "$TMPDIR/sqlite-plugin-tmp"
		mv "$TMPDIR/sqlite-plugin-tmp/sqlite-database-integration-main" "$SQLITE_PLUGIN_DIR"
		rm -rf "$TMPDIR/sqlite-plugin.zip" "$TMPDIR/sqlite-plugin-tmp"
	fi

	# Always refresh the db.php drop-in from the plugin so it stays up to date
	# The plugin ships the drop-in as db.copy (renamed to avoid auto-loading as a plugin file)
	cp "$SQLITE_PLUGIN_DIR/db.copy" "$WP_SQLITE_CONTENT_DIR/db.php"
}

install_sqlite_test_suite() {

	mkdir -p "$WP_TESTS_SQLITE_DIR"

	# Symlink includes and data from the MySQL test lib
	if [ ! -e "$WP_TESTS_SQLITE_DIR/includes" ]; then
		ln -s "$WP_TESTS_DIR/includes" "$WP_TESTS_SQLITE_DIR/includes"
	fi

	if [ ! -e "$WP_TESTS_SQLITE_DIR/data" ]; then
		ln -s "$WP_TESTS_DIR/data" "$WP_TESTS_SQLITE_DIR/data"
	fi

	if [ ! -f "$WP_TESTS_SQLITE_DIR/wp-tests-config.php" ]; then

		# portable in-place argument for both GNU sed and Mac OSX sed
		if [[ $(uname -s) == 'Darwin' ]]; then
			local ioption='-i.bak'
		else
			local ioption='-i'
		fi

		WP_CORE_DIR_CLEAN=$(echo $WP_CORE_DIR | sed "s:/\+$::")
		WP_SQLITE_CONTENT_CLEAN=$(echo $WP_SQLITE_CONTENT_DIR | sed "s:/\+$::")

		cat > "$WP_TESTS_SQLITE_DIR/wp-tests-config.php" << PHPEOF
<?php
define( 'ABSPATH', '${WP_CORE_DIR_CLEAN}/' );
define( 'WP_CONTENT_DIR', '${WP_SQLITE_CONTENT_CLEAN}' );
define( 'DB_NAME', 'wptest_sqlite' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'WP_TESTS_DOMAIN', 'wpftest.test' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
\$table_prefix = 'wptests_';
define( 'WPLANG', '' );
defined( 'WP_DEBUG_LOG' ) || define( 'WP_DEBUG_LOG', true );
PHPEOF
	fi
}

install_sqlite_content
install_sqlite_test_suite

echo "SQLite test environment ready."
