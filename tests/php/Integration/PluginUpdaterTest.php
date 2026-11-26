<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Services\PluginManager\PluginUpdater;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * The Check Update link on the Plugins screen carries a nonce, and its admin_init handler runs the
 * uncached update check only with that nonce, so another site can't make an admin's browser fire it.
 */
class PluginUpdaterTest extends IntegrationTestCase
{
    private const PARAM = 'fluent_sl_check_update_fluent-event';

    private const HOOKS = ['admin_init', 'plugin_row_meta', 'plugins_api', 'pre_set_site_transient_update_plugins', 'upgrader_pre_download'];

    /** @var array<int, array{0: string, 1: callable, 2: int}> hooks the updater under test registered */
    private $updaterHooks = [];

    protected function setUp(): void
    {
        parent::setUp();

        // The harness defines DISALLOW_FILE_MODS, which takes update_plugins from everyone; give it back to
        // administrators, as on a normal site.
        $this->addTestFilter('map_meta_cap', function ($caps, $cap) {
            return $cap === 'update_plugins' ? ['manage_options'] : $caps;
        }, 10, 2);

        // The handler ends in wp_safe_redirect() + exit(): stop every redirect before the exit, so a check that
        // runs when it shouldn't fails the test instead of silently ending the PHPUnit process.
        $this->addTestFilter('wp_redirect', function ($location) {
            throw new \RuntimeException((string) $location, 302);
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->updaterHooks as [$hook, $callback, $priority]) {
            remove_filter($hook, $callback, $priority);
        }

        unset($_GET[self::PARAM], $_GET['_wpnonce']);

        parent::tearDown();
    }

    public function test_the_check_update_link_carries_a_nonce_for_its_handler()
    {
        $this->actAs($this->createAdmin());

        $rowMeta = $this->registerUpdater()[1]['plugin_row_meta'] ?? null;
        $this->assertIsCallable($rowMeta, 'The updater registered no plugin_row_meta filter.');

        $links = $rowMeta([], plugin_basename(FLUENT_EVENT_DIR_FILE));

        $this->assertArrayHasKey('check_update', $links);
        $this->assertMatchesRegularExpression('/href="([^"]+)"/', $links['check_update']);
        preg_match('/href="([^"]+)"/', $links['check_update'], $href);
        parse_str((string) wp_parse_url(html_entity_decode($href[1]), PHP_URL_QUERY), $query);

        $this->assertArrayHasKey(self::PARAM, $query);
        $this->assertNotFalse(wp_verify_nonce($query['_wpnonce'] ?? '', self::PARAM));
    }

    public function test_the_check_is_refused_without_a_nonce()
    {
        $this->actAs($this->createAdmin());

        [$handler, $cacheKey] = $this->updaterHandling([]);

        $this->assertNull($this->runCheck($handler), 'The check ran and redirected.');

        $this->assertSame('cached', get_transient($cacheKey));
    }

    public function test_the_check_is_refused_with_a_wrong_nonce()
    {
        $this->actAs($this->createAdmin());

        [$handler, $cacheKey] = $this->updaterHandling(['_wpnonce' => 'deadbeef00']);

        $this->assertNull($this->runCheck($handler), 'The check ran and redirected.');

        $this->assertSame('cached', get_transient($cacheKey));
    }

    public function test_the_check_is_refused_to_a_user_who_cannot_update_plugins()
    {
        $this->actAs($this->createUser('editor'));

        [$handler, $cacheKey] = $this->updaterHandling(['_wpnonce' => wp_create_nonce(self::PARAM)]);

        $this->assertNull($this->runCheck($handler), 'The check ran and redirected.');

        $this->assertSame('cached', get_transient($cacheKey));
    }

    public function test_the_check_runs_with_a_valid_nonce()
    {
        $this->actAs($this->createAdmin());

        [$handler, $cacheKey] = $this->updaterHandling(['_wpnonce' => wp_create_nonce(self::PARAM)]);

        $this->assertStringContainsString('plugins.php?s=fluent-event', (string) $this->runCheck($handler));

        // Cleared, then looked up again uncached (outbound HTTP is blocked here, so that lookup fails).
        $this->assertNotSame('cached', get_transient($cacheKey), 'The cached version info was not refreshed.');
    }

    /**
     * Runs the admin_init handler.
     *
     * @return string|null where it redirected, or null when it returned without running the check
     */
    private function runCheck(callable $handler)
    {
        try {
            $handler();
        } catch (\RuntimeException $redirect) {
            if ($redirect->getCode() !== 302) {
                throw $redirect;
            }

            return $redirect->getMessage();
        }

        return null;
    }

    /**
     * Registers an updater for a Check Update request carrying $query, with a cached version
     * lookup in place, and returns its admin_init handler and that cache's key.
     *
     * @return array{0: callable, 1: string}
     */
    private function updaterHandling(array $query)
    {
        foreach ([self::PARAM => (string) time()] + $query as $key => $value) {
            $_GET[$key] = $value;
        }

        [$updater, $registered] = $this->registerUpdater();
        $handler = $registered['admin_init'] ?? null;

        $this->assertIsCallable($handler, 'The updater registered no admin_init handler for the request.');

        $cacheKey = (new \ReflectionProperty($updater, 'cache_key'));
        $cacheKey->setAccessible(true);
        $cacheKey = $cacheKey->getValue($updater);
        set_transient($cacheKey, 'cached', HOUR_IN_SECONDS);

        return [$handler, $cacheKey];
    }

    /**
     * Registers the plugin's updater, removed again in tearDown().
     *
     * @return array{0: PluginUpdater, 1: array<string, callable>} the updater, and the callback it added to each hook
     */
    private function registerUpdater()
    {
        $before = $this->callbacks();
        $updater = PluginUpdater::register(FLUENT_EVENT_DIR_FILE);
        $registered = [];

        foreach ($this->callbacks() as $id => [$hook, $callback, $priority]) {
            if (!isset($before[$id])) {
                $this->updaterHooks[] = [$hook, $callback, $priority];
                $registered[$hook] = $callback;
            }
        }

        return [$updater, $registered];
    }

    /** @return array<string, array{0: string, 1: callable, 2: int}> every callback on HOOKS, by WordPress's own id */
    private function callbacks()
    {
        global $wp_filter;
        $found = [];

        foreach (self::HOOKS as $hook) {
            if (empty($wp_filter[$hook])) {
                continue;
            }

            foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $id => $entry) {
                    $found[$hook . '|' . $priority . '|' . $id] = [$hook, $entry['function'], $priority];
                }
            }
        }

        return $found;
    }
}
