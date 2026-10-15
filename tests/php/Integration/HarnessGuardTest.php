<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Models\Meta;
use FluentEvent\Facade\DB;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Proves the containment every other integration test relies on. If one of
 * these breaks, the rest of the suite keeps looking green while writing to a
 * real database or mailing real people.
 */
class HarnessGuardTest extends IntegrationTestCase
{
    /** @var int|null */
    private static $probeId;

    public function test_a_transaction_is_open_during_the_test()
    {
        $active = $this->wpdb->get_var(
            'SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id = CONNECTION_ID()'
        );

        if ($active === null) {
            $this->markTestSkipped('No PROCESS privilege to read INNODB_TRX; tearDown\'s row count is the guarantee.');
        }

        $this->assertGreaterThanOrEqual(1, (int) $active);
    }

    public function test_a_write_is_visible_inside_the_test()
    {
        $before = $this->rowCount($this->table('fe_meta'));

        self::$probeId = Meta::query()->create([
            'object_type' => 'fe_harness_probe',
            'object_id'   => 0,
            'meta_key'    => 'rollback_probe',
            'value'       => ['ok' => true],
        ])->id;

        $this->assertSame($before + 1, $this->rowCount($this->table('fe_meta')));
    }

    /**
     * @depends test_a_write_is_visible_inside_the_test
     */
    public function test_that_write_is_gone_in_the_next_test()
    {
        $this->assertNull(Meta::query()->find(self::$probeId));
    }

    public function test_a_nested_db_transaction_does_not_commit_the_outer_one()
    {
        // EventService and EventController wrap writes in DB::transaction(); its commit must release a savepoint.
        DB::transaction(function () {
            Meta::query()->create(['object_type' => 'fe_harness_probe', 'object_id' => 0, 'meta_key' => 'nested', 'value' => []]);
        });

        $active = $this->wpdb->get_var(
            'SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id = CONNECTION_ID()'
        );

        if ($active !== null) {
            $this->assertGreaterThanOrEqual(1, (int) $active, 'DB::transaction() issued a real COMMIT');
        }

        $this->assertSame(1, Meta::query()->where('object_type', 'fe_harness_probe')->where('meta_key', 'nested')->count());
    }

    public function test_the_persistent_object_cache_is_off()
    {
        // Null, not false, when the drop-in skipped itself and never registered.
        $this->assertNotTrue(wp_using_ext_object_cache());

        $cacheFile = (new \ReflectionClass($GLOBALS['wp_object_cache']))->getFileName();
        $this->assertStringEndsWith('wp-includes/class-wp-object-cache.php', $cacheFile, 'The Redis cache is still in use');
    }

    public function test_mail_is_captured_not_sent()
    {
        $this->assertTrue(wp_mail('someone@example.test', 'Subject', 'Body'));
        $this->assertMailSentTo('someone@example.test');
    }

    public function test_outbound_http_is_blocked()
    {
        $response = wp_remote_get('https://example.com/', ['timeout' => 2, 'redirection' => 0]);

        $this->assertWPError($response);
        $this->assertSame(1, $this->blockedHttp);
    }

    public function test_the_rest_namespace_routes_to_this_plugin()
    {
        $this->actAs($this->createAdmin());

        $this->assertSame(200, $this->rest('GET', 'events', ['per_page' => 1])['status']);
    }

    public function test_validation_failures_surface_as_422_not_200()
    {
        // Without REST_REQUEST the framework swallows ValidationException; this proves the bootstrap set it.
        $this->actAs($this->createAdmin());

        $this->assertSame(422, $this->rest('POST', 'settings/general', ['default_events_view' => 'nonsense'])['status']);
    }

    private function assertWPError($thing)
    {
        $this->assertTrue(is_wp_error($thing), 'Expected a WP_Error');
    }
}
