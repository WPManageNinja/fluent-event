<?php

namespace FluentEvent\Tests\Unit\Services;

use Brain\Monkey\Functions;
use FluentEvent\App\Services\RateLimiter;
use FluentEvent\Tests\TestCase;
use Mockery;

/**
 * The registration rate limit: a sliding window per visitor and action, serialized by a MySQL lock.
 */
class RateLimiterTest extends TestCase
{
    private $originalWpdb;

    private $transients = [];

    /** @var string|null What GET_LOCK returns: '1' acquired, '0' timed out, null unsupported. */
    private $lockResult = '1';

    private $lockCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('DB_NAME')) {
            define('DB_NAME', 'wptest');
        }

        // A fresh wpdb per test: the shared one would keep these lock expectations for later tests.
        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wptest_';
        $wpdb->shouldReceive('suppress_errors')->andReturn(false);
        $wpdb->shouldReceive('prepare')->andReturnUsing(function ($query, ...$args) {
            return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
        });
        $wpdb->shouldReceive('get_var')->andReturnUsing(function ($sql) {
            $this->lockCalls[] = strtok($sql, '(');

            return strpos($sql, 'GET_LOCK') !== false ? $this->lockResult : '1';
        });
        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('get_transient')->alias(function ($key) {
            return $this->transients[$key] ?? false;
        });
        Functions\when('set_transient')->alias(function ($key, $value) {
            $this->transients[$key] = $value;

            return true;
        });
    }

    protected function tearDown(): void
    {
        $GLOBALS['wpdb'] = $this->originalWpdb;
        parent::tearDown();
    }

    public function test_hits_up_to_the_limit_pass_and_the_next_one_is_refused()
    {
        $this->assertFalse(RateLimiter::hit('k', 2, 60));
        $this->assertFalse(RateLimiter::hit('k', 2, 60));
        $this->assertTrue(RateLimiter::hit('k', 2, 60));
    }

    public function test_a_refused_hit_is_counted_so_a_caller_that_keeps_sending_stays_refused()
    {
        // One hit 50s ago fills a limit of 1; a fixed window would reopen at 60s, a sliding one
        // reopens only after a whole quiet window, because each refused hit is a hit too.
        $this->transients['k'] = [time() - 50];

        $this->assertTrue(RateLimiter::hit('k', 1, 60));
        $this->assertSame(2, count($this->transients['k']));

        // 15s later the first hit has left the window, but the refused one has not.
        $this->transients['k'] = [time() - 65, time() - 15];
        $this->assertTrue(RateLimiter::hit('k', 1, 60));
    }

    // The production guest limit. No cooldown after a refusal: a retry passes once the last
    // minute holds 5 hits or fewer, and still no minute accepts more than 5.
    public function test_a_refusal_has_no_cooldown_beyond_the_rolling_count()
    {
        // Five accepted at 0s; a sixth at 50s is refused.
        $this->transients['k'] = array_fill(0, 5, time() - 50);
        $this->assertTrue(RateLimiter::hit('k', 5, 60));

        // At 61s the five have left the window; only the refused one remains, so the retry passes.
        $this->transients['k'] = array_merge(array_fill(0, 5, time() - 61), [time() - 11]);
        $this->assertFalse(RateLimiter::hit('k', 5, 60));
        $this->assertCount(2, $this->transients['k']);
    }

    public function test_keys_are_counted_apart()
    {
        $this->assertFalse(RateLimiter::hit('a', 1, 60));
        $this->assertFalse(RateLimiter::hit('b', 1, 60));
    }

    public function test_hits_older_than_the_window_no_longer_count()
    {
        $this->transients['k'] = [time() - 61, time() - 61];

        $this->assertFalse(RateLimiter::hit('k', 1, 60));
        $this->assertCount(1, $this->transients['k']);
    }

    public function test_the_window_slides_rather_than_resetting_in_blocks()
    {
        // Two hits 40s ago: with a limit of 2 a third, now, is refused, as the window still holds them.
        $this->transients['k'] = [time() - 40, time() - 40];

        $this->assertTrue(RateLimiter::hit('k', 2, 60));
    }

    public function test_a_flood_stores_no_more_than_the_hits_that_decide()
    {
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit('k', 3, 60);
        }

        $this->assertCount(4, $this->transients['k']);
        $this->assertTrue(RateLimiter::hit('k', 3, 60));
    }

    public function test_a_stored_value_in_an_unknown_shape_starts_again()
    {
        $this->transients['k'] = ['count' => 5, 'expires' => time() + 60];

        $this->assertFalse(RateLimiter::hit('k', 1, 60));
    }

    public function test_the_count_runs_inside_the_lock_and_releases_it()
    {
        RateLimiter::hit('k', 1, 60);

        $this->assertSame(['SELECT GET_LOCK', 'SELECT RELEASE_LOCK'], $this->lockCalls);
    }

    public function test_a_lock_timeout_refuses_the_hit_without_counting()
    {
        $this->lockResult = '0';

        $this->assertTrue(RateLimiter::hit('k', 5, 60));
        $this->assertArrayNotHasKey('k', $this->transients);
    }

    private function visitor($ip)
    {
        return $this->callProtected(RateLimiter::class, 'visitor', [$ip]);
    }

    public function test_an_ipv4_address_is_its_own_visitor()
    {
        $this->assertSame('203.0.113.7', $this->visitor('203.0.113.7'));
        $this->assertNotSame($this->visitor('203.0.113.7'), $this->visitor('203.0.113.8'));
    }

    public function test_addresses_in_one_ipv6_64_are_one_visitor()
    {
        $this->assertSame($this->visitor('2001:db8:abcd:12::1'), $this->visitor('2001:db8:abcd:12:ffff:ffff:ffff:ffff'));
        $this->assertSame($this->visitor('2001:db8::1'), $this->visitor('2001:0db8:0000:0000:0000:0000:0000:0001'));
    }

    public function test_the_next_ipv6_64_is_another_visitor()
    {
        $this->assertNotSame($this->visitor('2001:db8:abcd:12::1'), $this->visitor('2001:db8:abcd:13::1'));
    }

    public function test_without_lock_support_it_still_counts()
    {
        $this->lockResult = null;

        $this->assertFalse(RateLimiter::hit('k', 1, 60));
        $this->assertTrue(RateLimiter::hit('k', 1, 60));
        $this->assertSame(['SELECT GET_LOCK', 'SELECT GET_LOCK'], $this->lockCalls);
    }
}
