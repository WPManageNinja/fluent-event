<?php

namespace FluentEvent\Tests\Unit\Database;

use FluentEvent\Database\Migrations\LegacyPrefixMigrator;
use FluentEvent\Tests\TestCase;

/**
 * Links shared before the `fe_` → `fevent` rename: the old query arg names are renamed and the
 * visitor is sent to the same URL, so the usual page checks still decide what they see.
 */
class LegacyPrefixLinksTest extends TestCase
{
    private function target(array $query, $uri = '/')
    {
        return LegacyPrefixMigrator::legacyLinkTarget($query, $uri);
    }

    public function test_an_old_plain_permalink_event_link_points_at_the_new_query_var()
    {
        $this->assertSame('/?fevent_event=launch', $this->target(['fe_event' => 'launch'], '/?fe_event=launch'));
    }

    public function test_the_events_list_filters_are_renamed_and_other_args_are_kept()
    {
        $target = $this->target(
            ['fe_calendar' => 'team', 'fe_page' => '2', 'fe_search' => 'yoga class', 'view' => 'cards'],
            '/events/?fe_calendar=team&fe_page=2&fe_search=yoga+class&view=cards'
        );

        // Renamed args follow LEGACY_QUERY_ARGS order, after the ones that were already there.
        $this->assertSame('/events/?view=cards&fevent_page=2&fevent_calendar=team&fevent_search=yoga%20class', $target);
    }

    public function test_a_link_that_already_has_the_new_name_keeps_its_value()
    {
        $this->assertSame('/?fevent_page=3', $this->target(['fe_page' => '2', 'fevent_page' => '3'], '/?fe_page=2&fevent_page=3'));
    }

    public function test_an_array_value_is_dropped_rather_than_passed_on()
    {
        $this->assertSame('/events/', $this->target(['fe_calendar' => ['a', 'b']], '/events/?fe_calendar[]=a&fe_calendar[]=b'));
    }

    public function test_a_link_without_old_names_is_left_alone()
    {
        $this->assertNull($this->target(['fevent_calendar' => 'team', 'view' => 'cards'], '/events/?fevent_calendar=team&view=cards'));
        $this->assertNull($this->target([], '/'));
    }

    public function test_the_redirect_never_leaves_the_site()
    {
        $this->assertSame('/evil.example/x?fevent_event=a', $this->target(['fe_event' => 'a'], '//evil.example/x?fe_event=a'));
    }
}
