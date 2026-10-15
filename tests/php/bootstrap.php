<?php
/**
 * Bootstrap for the unit tier. No WordPress, no database: shared WP functions
 * come from Helpers/wordpress-stubs.php, per-test ones from Brain\Monkey.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Patchwork must load before the stubs below are defined, or Brain\Monkey can't redefine them.
\Brain\Monkey\setUp();

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

date_default_timezone_set('UTC');

// From fluent-event.php, which can't be loaded here (ABSPATH guard, plugin_dir_path() at file scope).
define('FLUENT_EVENT_PLUGIN_VERSION', '1.0.0');
define('FLUENT_EVENT_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
define('FLUENT_EVENT_PLUGIN_URL', 'https://example.com/wp-content/plugins/fluent-events/');
define('FLUENT_EVENT_DIR_FILE', FLUENT_EVENT_PLUGIN_DIR . 'fluent-event.php');
define('FLUENT_EVENT_START_TIME', microtime(true));

define('ABSPATH', '/tmp/wordpress/');
define('WPINC', 'wp-includes');
define('WP_DEBUG', true);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS);
define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS);
define('WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS);
define('MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS);
define('YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS);

// Backing stores for the option and cache stubs, reset in TestCase::setUp().
$GLOBALS['feTestOptions'] = [];
$GLOBALS['feTestObjectCache'] = [];

require_once __DIR__ . '/Helpers/wordpress-stubs.php';

// boot/globals.php reads $app->config at file scope to decide on dev/globals.php.
$app = new class {
    public $config;

    public function __construct()
    {
        $this->config = new class {
            public function get($key, $default = null)
            {
                return $key === 'app.env' ? 'production' : $default;
            }
        };
    }
};

require_once dirname(__DIR__, 2) . '/boot/globals.php';

unset($app);
