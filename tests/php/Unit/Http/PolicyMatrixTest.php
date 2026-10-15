<?php

namespace FluentEvent\Tests\Unit\Http;

use FluentEvent\App\Http\Policies\CalendarPolicy;
use FluentEvent\App\Http\Policies\CommunityPolicy;
use FluentEvent\App\Http\Policies\EventPolicy;
use FluentEvent\App\Http\Policies\ReportPolicy;
use FluentEvent\App\Http\Policies\SettingsPolicy;
use FluentEvent\App\Http\Policies\UserPolicy;
use FluentEvent\App\Services\PermissionManager;
use FluentEvent\Framework\Http\Request\Request;
use FluentEvent\Tests\TestCase;

/**
 * Which permission key opens which policy method. A read key must never pass a
 * write method; the team endpoints need the manage_all_data superkey.
 */
class PolicyMatrixTest extends TestCase
{
    const STAFF_POLICIES = [
        EventPolicy::class,
        CalendarPolicy::class,
        SettingsPolicy::class,
        ReportPolicy::class,
        UserPolicy::class,
    ];

    protected function tearDown(): void
    {
        $this->setStaticProperty(PermissionManager::class, 'cache', []);

        parent::tearDown();
    }

    private function grant(array $permissions)
    {
        // Seeding the per-request cache for the current user (ID 1) skips the fe_meta lookup.
        $this->setStaticProperty(PermissionManager::class, 'cache', [1 => $permissions]);
    }

    private function allows($policyClass, $method)
    {
        // Mockery can't double Request (its __call signature clashes); no policy reads it anyway.
        $request = (new \ReflectionClass(Request::class))->newInstanceWithoutConstructor();

        return (new $policyClass())->{$method}($request);
    }

    /**
     * @dataProvider matrix
     */
    public function test_a_permission_opens_exactly_the_methods_it_should($policy, $method, array $granted, $expected)
    {
        $this->grant($granted);

        $this->assertSame($expected, $this->allows($policy, $method));
    }

    public function matrix()
    {
        return [
            'events: read key may list'              => [EventPolicy::class, 'verifyRequest', ['read_events'], true],
            'events: read key may not update'        => [EventPolicy::class, 'updateEvent', ['read_events'], false],
            'events: read key may not delete'        => [EventPolicy::class, 'deleteEvent', ['read_events'], false],
            'events: write key may update'           => [EventPolicy::class, 'updateEvent', ['write_events'], true],
            'events: event key may not list guests'  => [EventPolicy::class, 'getGuests', ['write_events'], false],
            'guests: read key may list guests'       => [EventPolicy::class, 'getGuests', ['read_guests'], true],
            'guests: read key may not add a guest'   => [EventPolicy::class, 'createGuest', ['read_guests'], false],
            'guests: read key may not remove'        => [EventPolicy::class, 'deleteGuest', ['read_guests'], false],
            'guests: write key may add a guest'      => [EventPolicy::class, 'createGuest', ['write_guests'], true],
            'guests: guest key may not open events'  => [EventPolicy::class, 'verifyRequest', ['read_guests'], false],
            'calendars: read key may list'           => [CalendarPolicy::class, 'verifyRequest', ['read_calendars'], true],
            'calendars: read key may not delete'     => [CalendarPolicy::class, 'deleteCalendar', ['read_calendars'], false],
            'calendars: write key may update'        => [CalendarPolicy::class, 'updateCalendar', ['write_calendars'], true],
            'calendars: event key may load options'  => [CalendarPolicy::class, 'getCalendarOptions', ['write_events'], true],
            'calendars: event key may not list'      => [CalendarPolicy::class, 'verifyRequest', ['read_events'], false],
            'calendars: guest key may not load options' => [CalendarPolicy::class, 'getCalendarOptions', ['read_guests'], false],
            'settings: read key may view'            => [SettingsPolicy::class, 'verifyRequest', ['read_settings'], true],
            'settings: read key may not save'        => [SettingsPolicy::class, 'updateEmail', ['read_settings'], false],
            'settings: write key may save'           => [SettingsPolicy::class, 'updateGeneral', ['write_settings'], true],
            'team: settings writer may not see team' => [SettingsPolicy::class, 'getTeam', ['write_settings'], false],
            'team: settings writer may not grant'    => [SettingsPolicy::class, 'upsertTeamMember', ['write_settings'], false],
            'team: superkey may grant'               => [SettingsPolicy::class, 'upsertTeamMember', [PermissionManager::ALL_DATA], true],
            'dashboard: any key opens it'            => [ReportPolicy::class, 'verifyRequest', ['read_guests'], true],
            'dashboard: no key keeps it shut'        => [ReportPolicy::class, 'verifyRequest', [], false],
            'user search: needs guest write'         => [UserPolicy::class, 'verifyRequest', ['write_guests'], true],
            'user search: guest read is not enough'  => [UserPolicy::class, 'verifyRequest', ['read_guests'], false],
        ];
    }

    public function test_the_superkey_opens_every_staff_policy_method()
    {
        $this->grant([PermissionManager::ALL_DATA]);

        foreach ($this->policyMethods() as [$policy, $method]) {
            $this->assertTrue($this->allows($policy, $method), "manage_all_data was refused {$policy}::{$method}");
        }
    }

    public function test_a_user_with_no_permissions_is_refused_everywhere()
    {
        $this->grant([]);

        foreach ($this->policyMethods() as [$policy, $method]) {
            $this->assertFalse($this->allows($policy, $method), "No permissions, yet {$policy}::{$method} allowed it");
        }
    }

    public function test_community_routes_stay_shut_without_fluentcommunity_even_for_the_superkey()
    {
        $this->grant([PermissionManager::ALL_DATA]);

        $this->assertFalse(defined('FLUENT_COMMUNITY_PLUGIN_VERSION'), 'The unit tier must run without fcom');

        foreach (['verifyRequest', 'register', 'getEditorAssets'] as $method) {
            $this->assertFalse($this->allows(CommunityPolicy::class, $method), "CommunityPolicy::{$method} opened without fcom");
        }
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function policyMethods()
    {
        $methods = [];

        foreach (self::STAFF_POLICIES as $policy) {
            foreach ((new \ReflectionClass($policy))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() === $policy) {
                    $methods[] = [$policy, $method->getName()];
                }
            }
        }

        return $methods;
    }
}
