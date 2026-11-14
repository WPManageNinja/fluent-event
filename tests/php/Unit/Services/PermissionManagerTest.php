<?php

namespace FluentEvent\Tests\Unit\Services;

use Brain\Monkey\Functions;
use FluentEvent\App\Services\PermissionManager;
use FluentEvent\Tests\TestCase;

class PermissionManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->setStaticProperty(PermissionManager::class, 'cache', []);

        parent::tearDown();
    }

    private function grant($userId, array $permissions)
    {
        $this->setStaticProperty(PermissionManager::class, 'cache', [$userId => $permissions]);
    }

    public function test_the_permission_keys_are_the_flat_module_set()
    {
        // Adding a key is a security decision; this makes it a visible one.
        $this->assertSame([
            'read_calendars', 'write_calendars',
            'read_events', 'write_events',
            'read_guests', 'write_guests',
            'read_settings', 'write_settings',
            PermissionManager::ALL_DATA,
        ], array_keys(PermissionManager::allPermissionSets()));
    }

    public function test_an_explicit_zero_user_has_no_permissions_even_when_someone_is_logged_in()
    {
        $this->grant(1, [PermissionManager::ALL_DATA]);

        $this->assertSame([], PermissionManager::getUserPermissions(0));
        $this->assertFalse(PermissionManager::userCan('read_events', 0));
    }

    public function test_null_resolves_to_the_current_user()
    {
        $this->grant(1, ['read_events']);

        $this->assertSame(['read_events'], PermissionManager::getUserPermissions());
    }

    public function test_an_administrator_resolves_to_the_superkey_without_a_lookup()
    {
        Functions\when('user_can')->alias(function ($userId, $capability) {
            return $userId === 7 && $capability === 'manage_options';
        });

        $this->assertSame([PermissionManager::ALL_DATA], PermissionManager::getUserPermissions(7));
    }

    public function test_without_pro_a_non_administrator_holds_no_keys_whatever_is_stored()
    {
        $this->assertFalse(defined('FLUENT_EVENT_PRO'), 'The unit tier runs the free plugin alone');
        Functions\when('user_can')->justReturn(false);

        // No database here: reaching the stored-row lookup would error, so [] proves it was skipped.
        $this->assertSame([], PermissionManager::getUserPermissions(5));
        $this->assertFalse(PermissionManager::userCan('read_events', 5));
    }

    public function test_any_match_passes_when_one_permission_is_held()
    {
        $this->grant(1, ['read_events']);

        $this->assertTrue(PermissionManager::userCan(['read_events', 'write_events']));
        $this->assertFalse(PermissionManager::userCan('write_events'));
    }

    public function test_all_match_needs_every_permission()
    {
        $this->grant(1, ['read_events']);

        $this->assertFalse(PermissionManager::userCan(['read_events', 'write_events'], null, ['match_type' => 'all']));
        $this->assertTrue(PermissionManager::userCan(['read_events'], null, ['match_type' => 'all']));
    }

    public function test_the_superkey_satisfies_any_check()
    {
        $this->grant(1, [PermissionManager::ALL_DATA]);

        $this->assertTrue(PermissionManager::userCan('write_settings'));
        $this->assertTrue(PermissionManager::userCan(['read_events', 'write_guests'], null, ['match_type' => 'all']));
    }

    public function test_has_any_permission_is_false_for_a_user_with_none()
    {
        $this->grant(1, []);
        $this->assertFalse(PermissionManager::hasAnyPermission());

        $this->grant(1, ['read_guests']);
        $this->assertTrue(PermissionManager::hasAnyPermission());
    }

    public function test_administrators_get_manage_options_on_every_menu_item()
    {
        $this->grant(1, [PermissionManager::ALL_DATA]);
        Functions\when('current_user_can')->justReturn(true);

        $this->assertSame(
            ['dashboard' => 'manage_options', 'calendars' => 'manage_options', 'events' => 'manage_options', 'add_event' => 'manage_options', 'settings' => 'manage_options'],
            PermissionManager::getMenuCapabilities()
        );
    }

    public function test_a_team_member_sees_only_the_menus_their_keys_open()
    {
        $this->grant(1, ['read_events']);
        Functions\when('wp_get_current_user')->justReturn((object) ['roles' => ['editor']]);

        // An empty capability hides the item; the member's own role shows it.
        $this->assertSame(
            ['dashboard' => 'editor', 'calendars' => '', 'events' => 'editor', 'add_event' => '', 'settings' => ''],
            PermissionManager::getMenuCapabilities()
        );
    }
}
