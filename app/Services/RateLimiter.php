<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Utils\Utility;
use FluentEvent\Framework\Foundation\App;

/**
 * Sliding-window counters that hold under parallel requests.
 *
 * A MySQL named lock serializes the read-increment-write, so parallel requests
 * cannot all read the same count. Transients, not wp_cache_incr(): some Redis
 * ACLs deny INCRBY.
 */
class RateLimiter
{
    /**
     * Count one attempt at $action by the current visitor: a member by account, anyone else by IP.
     *
     * Call it from the controller action, which runs once per request. A route's permission
     * callback runs again for the Allow header, so a count there would be taken twice.
     *
     * @param string $action Names the counter. Actions that share a name share the limit,
     *                       across every event, so a new event ID is not a new allowance.
     * @param int $limit Allowed attempts per window.
     * @param int $window Window length in seconds.
     * @return bool True when over the limit.
     */
    public static function isSpamming($action, $limit, $window = MINUTE_IN_SECONDS)
    {
        $who = is_user_logged_in() ? 'user:' . get_current_user_id() : static::visitor(Utility::getIp());
        $key = App::config()->get('app.slug') . '_rate_limit_' . md5($who . '|' . $action);

        return self::hit($key, $limit, $window);
    }

    // One IPv6 /64 is one visitor: a single connection is usually handed the whole range.
    // IPv4 stays as it is.
    protected static function visitor($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'v6:' . bin2hex(substr(inet_pton($ip), 0, 8));
        }

        return (string) $ip;
    }

    /**
     * Count one hit against $key.
     *
     * Lock timeout (contention) fails closed. No lock support at all (Galera,
     * SQLite) counts without the lock, so registration keeps working there.
     *
     * @param string $key Transient key.
     * @param int $limit Allowed hits in any $window seconds.
     * @param int $window Length of the sliding window, in seconds.
     * @return bool True when over the limit: more than $limit hits, refused ones included,
     *              in the last $window seconds. Counting refused hits keeps a caller who
     *              sends faster than the limit refused for as long as it keeps sending.
     *              There is no cooldown: once the window holds $limit hits or fewer, the
     *              next hit passes, so no $window ever accepts more than $limit.
     */
    public static function hit($key, $limit, $window)
    {
        global $wpdb;

        // GET_LOCK is server-wide, so the database and prefix keep two sites on one server apart.
        $lockName = 'fevent_rl_' . md5(constant('DB_NAME') . ':' . $wpdb->prefix . ':' . $key);

        $suppressed = $wpdb->suppress_errors(true);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- MySQL advisory lock; a cached result would defeat the lock
        $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lockName, 2));
        $wpdb->suppress_errors($suppressed);

        if ($locked === null) {
            return self::count($key, $limit, $window);
        }

        if ((string) $locked !== '1') {
            return true;
        }

        try {
            return self::count($key, $limit, $window);
        } finally {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- MySQL advisory lock; a cached result would defeat the lock
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }
    }

    private static function count($key, $limit, $window)
    {
        $now = time();
        $window = max(1, (int) $window);
        $hits = get_transient($key);

        // Each hit is the time it arrived. Only those inside the last $window seconds count,
        // so the limit holds over any stretch of that length, not per fixed block.
        $hits = array_values(array_filter(is_array($hits) ? $hits : [], function ($time) use ($now, $window) {
            return is_int($time) && $time <= $now && ($now - $time) <= $window;
        }));

        $hits[] = $now;

        // The newest $limit + 1 hits decide the answer, so a flood cannot grow the stored list.
        $hits = array_slice($hits, -((int) $limit + 1));

        set_transient($key, $hits, $window);

        return count($hits) > $limit;
    }
}
