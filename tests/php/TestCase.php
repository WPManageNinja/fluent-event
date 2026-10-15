<?php

namespace FluentEvent\Tests;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Unit-tier base: Brain\Monkey per test, empty option/cache stores and
 * defaults for the WordPress functions nearly every test touches.
 */
abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $GLOBALS['feTestOptions'] = [];
        $GLOBALS['feTestObjectCache'] = [];

        $this->setUpWordPressFunctions();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * A later Functions\when() in a test overrides any of these.
     */
    protected function setUpWordPressFunctions()
    {
        global $wpdb;

        if (!isset($wpdb)) {
            $wpdb = Mockery::mock('wpdb');

            // Not 'wp_', so a hardcoded table name can't pass for a prefixed one.
            $wpdb->prefix = 'wptest_';
            $wpdb->base_prefix = 'wptest_';

            $wpdb->shouldReceive('prepare')->andReturnUsing(function ($query, ...$args) {
                if (isset($args[0]) && is_array($args[0])) {
                    $args = $args[0];
                }

                return vsprintf(str_replace(['%s', '%d', '%f'], ["'%s'", '%d', '%f'], $query), $args);
            });

            $wpdb->shouldReceive('get_results')->andReturn([]);
            $wpdb->shouldReceive('get_row')->andReturn(null);
            $wpdb->shouldReceive('get_var')->andReturn(null);
            $wpdb->shouldReceive('get_col')->andReturn([]);
            $wpdb->shouldReceive('query')->andReturn(0);
        }

        // Identity, so assertions read on the input.
        Monkey\Functions\when('__')->returnArg();
        Monkey\Functions\when('_x')->returnArg();
        Monkey\Functions\when('_n')->returnArg();
        Monkey\Functions\when('esc_html__')->returnArg();
        Monkey\Functions\when('esc_attr__')->returnArg();
        Monkey\Functions\when('esc_html')->returnArg();
        Monkey\Functions\when('esc_attr')->returnArg();
        Monkey\Functions\when('esc_url')->returnArg();
        Monkey\Functions\when('wp_kses_post')->returnArg();

        // Filters pass their value through, actions do nothing.
        Monkey\Functions\when('apply_filters')->alias(function ($hook, $value = null, ...$args) {
            return $value;
        });
        Monkey\Functions\when('do_action')->justReturn(null);
        Monkey\Functions\when('did_action')->justReturn(0);
        Monkey\Functions\when('add_action')->justReturn(true);
        Monkey\Functions\when('add_filter')->justReturn(true);

        // Logged in as ID 1 with no capabilities: a permission test states what it grants.
        Monkey\Functions\when('get_current_user_id')->justReturn(1);
        Monkey\Functions\when('get_current_blog_id')->justReturn(1);
        Monkey\Functions\when('is_user_logged_in')->justReturn(true);
        Monkey\Functions\when('current_user_can')->justReturn(false);
        Monkey\Functions\when('user_can')->justReturn(false);
        Monkey\Functions\when('is_multisite')->justReturn(false);
    }

    /**
     * @param object|string $objectOrClass
     */
    protected function getPrivateProperty($objectOrClass, string $property)
    {
        $prop = new \ReflectionProperty($objectOrClass, $property);
        $this->makeAccessible($prop);

        return $prop->getValue(is_object($objectOrClass) ? $objectOrClass : null);
    }

    /**
     * @param object|string $objectOrClass
     */
    protected function setStaticProperty($objectOrClass, string $property, $value)
    {
        $prop = new \ReflectionProperty($objectOrClass, $property);
        $this->makeAccessible($prop);
        $prop->setValue(null, $value);
    }

    /**
     * @param object|string $objectOrClass
     */
    protected function callProtected($objectOrClass, string $method, array $args = [])
    {
        $handle = new \ReflectionMethod($objectOrClass, $method);
        $this->makeAccessible($handle);

        return $handle->invokeArgs(is_object($objectOrClass) ? $objectOrClass : null, $args);
    }

    // A no-op since PHP 8.1 and deprecated in 8.5, but still required on 7.4.
    private function makeAccessible($reflector)
    {
        if (PHP_VERSION_ID < 80100) {
            $reflector->setAccessible(true);
        }
    }
}
