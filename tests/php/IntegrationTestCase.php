<?php

namespace FluentEvent\Tests;

use FluentEvent\App\App;
use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Services\EventService;
use FluentEvent\App\Services\Settings;
use FluentEvent\Framework\Http\Request\Request;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Integration-tier base: real WordPress and database, each test inside a
 * transaction that tearDown() rolls back and then proves was rolled back.
 */
abstract class IntegrationTestCase extends BaseTestCase
{
    /** @var \wpdb */
    protected $wpdb;

    /** @var array<int, array{to: mixed, subject: string, message: string}> */
    protected $sentMail = [];

    /** @var int */
    protected $blockedHttp = 0;

    /** @var mixed */
    private $connection;

    /** @var int */
    private $sentinelId;

    /** @var int */
    private $previousUser;

    /** @var array<int, array{0: string, 1: callable, 2: int}> */
    private $filters = [];

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->wpdb = $wpdb;
        $this->previousUser = get_current_user_id();

        $this->blockOutboundSideEffects();
        $this->resetStaticCaches();

        /*
         * Open the transaction through the framework's connection, not a raw START
         * TRANSACTION: DB::transaction() then sees one already open, takes a SAVEPOINT,
         * and its commit releases that instead of issuing a real COMMIT.
         */
        $this->connection = App::make('db');
        $this->connection->beginTransaction();

        // Written inside the transaction: if anything commits during the test, this row survives the rollback.
        $wpdb->insert($wpdb->prefix . 'fe_meta', [
            'object_type' => 'fe_rollback_sentinel',
            'object_id'   => 0,
            'meta_key'    => wp_generate_password(20, false, false),
            'value'       => '[]',
        ]);
        $this->sentinelId = (int) $wpdb->insert_id;

        if (!$this->sentinelId) {
            $this->fail('Could not write the rollback sentinel; refusing to run without it.');
        }

        if (!$this->inTransaction()) {
            $this->fail('No transaction is open on this connection; every write would persist. Refusing to run.');
        }
    }

    protected function tearDown(): void
    {
        // Two independent signals that something committed mid-test: the transaction is gone before
        // the rollback, or the sentinel written inside it survives the rollback.
        $transactionLost = !$this->inTransaction();

        $this->connection->rollBack();

        // In case a nested commit escaped, end anything still open rather than leak it to the next test.
        $this->wpdb->query('ROLLBACK');

        foreach ($this->filters as [$hook, $callback, $priority]) {
            remove_filter($hook, $callback, $priority);
        }

        wp_set_current_user($this->previousUser);
        wp_cache_flush();

        $sentinelTable = $this->wpdb->prefix . 'fe_meta';
        $sentinelSurvived = (bool) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$sentinelTable} WHERE id = %d", $this->sentinelId)
        );

        if ($sentinelSurvived) {
            $this->wpdb->delete($sentinelTable, ['id' => $this->sentinelId]);
        }

        parent::tearDown();

        if ($transactionLost || $sentinelSurvived) {
            $this->fail(
                'Something in this test committed (most likely DDL), so everything it wrote before that '
                . 'point is now permanent in the database. See tests/php/integration-bootstrap.php.'
            );
        }
    }

    /**
     * Dispatch a REST request in-process, as the current user.
     *
     * @return array{status: int, data: array}
     */
    protected function rest($method, $route, array $params = [])
    {
        // The framework binds Request as a singleton and array_merges each dispatch into it,
        // so without a fresh one a later request sees an earlier one's parameters.
        $this->resetFrameworkRequest();

        $config = App::make('config');
        $namespace = '/' . $config->get('app.rest_namespace') . '/' . $config->get('app.rest_version');

        // The rate limiter reads these, and a real HTTP request always has them.
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $request = new \WP_REST_Request($method, $namespace . '/' . ltrim($route, '/'));
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

        if ($method === 'GET') {
            $request->set_query_params($params);
        } else {
            $request->set_body_params($params);
        }

        $response = rest_do_request($request);
        $data = rest_get_server()->response_to_data($response, false);

        return [
            'status' => $response->get_status(),
            'data'   => json_decode(wp_json_encode($data), true) ?: [],
        ];
    }

    protected function actAs($userId)
    {
        wp_set_current_user($userId);
    }

    protected function createUser($role = 'editor')
    {
        $unique = strtolower(wp_generate_password(10, false, false));

        $userId = wp_insert_user([
            'user_login'   => 'fe_test_' . $unique,
            'user_email'   => 'fe_test_' . $unique . '@example.test',
            'user_pass'    => wp_generate_password(24),
            'display_name' => 'Test ' . ucfirst($role),
            'role'         => $role,
        ]);

        $this->assertIsInt($userId, is_wp_error($userId) ? $userId->get_error_message() : 'wp_insert_user failed');

        return $userId;
    }

    protected function createAdmin()
    {
        return $this->createUser('administrator');
    }

    // The All Events Page, published and set: [fluent_events] at `fe-test-fluent-events-*`.
    protected function createPublicPages()
    {
        $ids = ['events_page_id' => wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => '',
            'post_name'    => 'fe-test-fluent-events-' . strtolower(wp_generate_password(6, false, false)),
            'post_content' => '[fluent_events]',
        ])];

        Settings::update(Settings::GENERAL, $ids);

        return $ids;
    }

    protected function createCalendar(array $overrides = [])
    {
        return Calendar::create(array_merge([
            'title'  => 'Integration calendar',
            'slug'   => 'fe-test-' . strtolower(wp_generate_password(10, false, false)),
            'status' => 'public',
            'meta'   => Calendar::defaultMeta(),
        ], $overrides));
    }

    /**
     * @param array $overrides EventRequest::format()-shaped data
     */
    protected function createEvent(array $overrides = [], array $meta = [])
    {
        $calendarId = $overrides['calendar_id'] ?? $this->createCalendar()->id;

        return EventService::createEvent(array_merge([
            'calendar_id' => $calendarId,
            'title'       => 'Integration event',
            'description' => '<p>Written by the integration suite; rolled back.</p>',
            'status'      => 'public',
            'start_at'    => gmdate('Y-m-d H:i:s', strtotime('+3 days')),
            'end_at'      => gmdate('Y-m-d H:i:s', strtotime('+3 days +2 hours')),
            'timezone'    => 'UTC',
            'venue_type'  => 'offline',
            'meta'        => array_merge(Event::defaultMeta(), ['entry_type' => 'rsvp', 'venue' => 'Hall A'], $meta),
        ], $overrides));
    }

    protected function createGuest(Event $event, array $overrides = [])
    {
        $unique = strtolower(wp_generate_password(8, false, false));

        return Guest::create(array_merge([
            'event_id'    => $event->id,
            'calendar_id' => $event->calendar_id,
            'group_id'    => wp_generate_password(32, false, false),
            'is_buyer'    => 1,
            'name'        => 'Guest ' . $unique,
            'email'       => 'guest_' . $unique . '@example.test',
            'status'      => 'going',
            'source'      => 'manual',
        ], $overrides));
    }

    /**
     * The event payload the admin edit form sends, for PATCH /events/{id}.
     */
    protected function eventPayload(Event $event, array $overrides = [])
    {
        return array_merge([
            'calendar_id'      => $event->calendar_id,
            'title'            => $event->title,
            'description_md'   => (string) (((array) $event->meta)['description_md'] ?? ''),
            'status'           => $event->status,
            'start_at'         => $event->start_at_local,
            'end_at'           => $event->end_at_local,
            'timezone'         => $event->timezone,
            'venue_type'       => $event->venue_type,
            'venue'            => 'Hall A',
            'address'          => '',
            'entry_type'       => 'rsvp',
            'show_guest_count' => 'yes',
        ], $overrides);
    }

    protected function rowCount($table)
    {
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    protected function table($name)
    {
        return $this->wpdb->prefix . $name;
    }

    protected function addTestFilter($hook, callable $callback, $priority = 10, $args = 1)
    {
        add_filter($hook, $callback, $priority, $args);
        $this->filters[] = [$hook, $callback, $priority];
    }

    protected function assertMailSentTo($email, $message = '')
    {
        $this->assertContains($email, array_column($this->sentMail, 'to'), $message ?: "No mail was attempted to {$email}");
    }

    protected function assertNoMailSent($message = '')
    {
        $this->assertSame([], $this->sentMail, $message ?: 'Mail was attempted when none should have been');
    }

    protected function hasQueuedAction($hook, array $args)
    {
        return as_has_scheduled_action($hook, $args, 'fluent-event');
    }

    /**
     * A savepoint only exists inside an open transaction, so rolling back to one fails once
     * something has committed. Real-time and needs no privilege, unlike INNODB_TRX, which MySQL
     * caches for up to 100ms and so still shows a transaction that was just committed.
     */
    protected function inTransaction()
    {
        $suppress = $this->wpdb->suppress_errors(true);

        $this->wpdb->query('SAVEPOINT fe_transaction_probe');
        $open = $this->wpdb->query('ROLLBACK TO SAVEPOINT fe_transaction_probe') !== false;

        if ($open) {
            $this->wpdb->query('RELEASE SAVEPOINT fe_transaction_probe');
        }

        $this->wpdb->suppress_errors($suppress);

        return $open;
    }

    // Rows these static caches hold were read inside a transaction that is now gone.
    private function resetStaticCaches()
    {
        $this->resetStaticProperty(Settings::class, 'stored');
    }

    private function resetStaticProperty($class, $property)
    {
        $reflection = new \ReflectionProperty($class, $property);

        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        $reflection->setValue(null, []);
    }

    private function resetFrameworkRequest()
    {
        $app = App::getInstance();
        $fresh = new Request($app, [], []);

        $app->singleton(Request::class, function () use ($fresh) {
            return $fresh;
        });

        $app->alias(Request::class, 'request');
    }

    private function blockOutboundSideEffects()
    {
        $this->addTestFilter('pre_wp_mail', function ($return, $atts) {
            $this->sentMail[] = [
                'to'      => is_array($atts['to']) ? implode(',', $atts['to']) : $atts['to'],
                'subject' => (string) $atts['subject'],
                'message' => (string) $atts['message'],
            ];

            return true;
        }, PHP_INT_MAX, 2);

        $this->addTestFilter('pre_http_request', function ($preempt, $args, $url) {
            $this->blockedHttp++;

            return new \WP_Error('fe_integration_blocked', 'Outbound HTTP blocked by the integration harness');
        }, PHP_INT_MAX, 3);
    }
}
