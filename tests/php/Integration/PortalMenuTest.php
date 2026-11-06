<?php

namespace FluentEvent\Tests\Integration;

use FluentCommunity\App\Functions\Utility as FcomUtility;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\Modules\FluentCommunity\PortalHandler;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * The portal's top Events link exists only while FluentEvent supplies it: fcom keeps the
 * admin's saved label and order, and drops the saved row once nothing supplies it.
 */
class PortalMenuTest extends IntegrationTestCase
{
    const OPTION_CACHE_KEY = 'option_fluent_community_menu_groups';

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            $this->markTestSkipped('FluentCommunity is not active.');
        }
    }

    protected function tearDown(): void
    {
        // fcom reads its options through the object cache, which the rollback does not reach.
        FcomUtility::forgetCache(self::OPTION_CACHE_KEY);

        parent::tearDown();
    }

    private function mainMenu()
    {
        FcomUtility::setCache(self::OPTION_CACHE_KEY, ['mainMenuItems' => [
            PortalHandler::MENU_SLUG => [
                'slug'      => PortalHandler::MENU_SLUG,
                'title'     => 'Meetups',
                'is_system' => 'yes',
                'enabled'   => 'yes',
                'permalink' => 'https://stale.test/events',
            ],
            'docs'                   => [
                'slug'      => 'docs',
                'title'     => 'Docs',
                'is_custom' => 'yes',
                'is_system' => 'no',
                'enabled'   => 'yes',
                'permalink' => 'https://docs.test/',
            ],
        ]]);

        return FcomHelper::getMenuItemsGroup('edit')['mainMenuItems'];
    }

    public function test_the_saved_events_link_keeps_the_admins_label_and_takes_our_link()
    {
        $item = $this->mainMenu()[PortalHandler::MENU_SLUG];

        $this->assertSame('Meetups', $item['title']);
        $this->assertSame(FcomHelper::baseUrl('events'), $item['permalink']);
    }

    public function test_the_saved_events_link_is_dropped_once_nothing_supplies_it()
    {
        // As with FluentEvent deactivated: nothing adds the item.
        $this->addTestFilter('fluent_community/default_main_menu_items', function ($items) {
            unset($items[PortalHandler::MENU_SLUG]);

            return $items;
        }, 99);

        $items = $this->mainMenu();

        $this->assertArrayNotHasKey(PortalHandler::MENU_SLUG, $items);
        $this->assertArrayHasKey('docs', $items);
    }
}
