<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Utils\Enqueuer\Enqueue;
use FluentEvent\Modules\FluentCommunity\PortalHandler;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * How the portal script and stylesheet reach fcom's page: fcom's add-on module runtime loads
 * them when it has one; on older fcom, printAssets() prints them.
 */
class PortalAssetsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            $this->markTestSkipped('FluentCommunity is not active.');
        }

        if (!Enqueue::url('fcom-portal/fcom-portal.js')) {
            $this->markTestSkipped('No portal script URL — run `npm run build` first.');
        }
    }

    public function test_fcom_places_our_script_after_its_start_and_before_its_app()
    {
        if (!method_exists(\FluentCommunity\App\Vite::class, 'insertModuleScripts')) {
            $this->markTestSkipped('This FluentCommunity has no add-on module runtime.');
        }

        $vars = \FluentCommunity\App\Vite::insertModuleScripts([
            'js_files' => [
                'fcom_start' => ['url' => 'start.js', 'deps' => []],
                'fcom_app'   => ['url' => 'app.js', 'deps' => []],
            ],
        ]);

        // Other add-ons (Pro) may sit beside ours, so only the order around fcom's own two is fixed.
        $order = array_flip(array_keys($vars['js_files']));

        $this->assertArrayHasKey('fluent_event_fcom_portal', $order);
        $this->assertGreaterThan($order['fcom_start'], $order['fluent_event_fcom_portal']);
        $this->assertLessThan($order['fcom_app'], $order['fluent_event_fcom_portal']);
        $this->assertSame(Enqueue::url('fcom-portal/fcom-portal.js'), $vars['js_files']['fluent_event_fcom_portal']['url']);
    }

    public function test_with_the_module_runtime_fcom_loads_the_files_and_we_print_only_the_vars()
    {
        $handler = $this->handler(true);

        $vars = $handler->addStylesheet(['css_files' => ['fcom_global' => ['url' => 'global.css']]]);
        $this->assertSame('fluent_event_fcom_portal', array_key_last($vars['css_files']));

        $html = $this->printed($handler);
        $this->assertStringContainsString('window.fluentEventFcomPortal', $html);
        $this->assertStringNotContainsString('<script type="module"', $html);
        $this->assertStringNotContainsString('<link', $html);
    }

    public function test_without_the_module_runtime_we_print_the_script_and_stylesheet()
    {
        $handler = $this->handler(false);

        $vars = ['css_files' => ['fcom_global' => ['url' => 'global.css']]];
        $this->assertSame($vars, $handler->addStylesheet($vars));

        $html = $this->printed($handler);
        $this->assertStringContainsString('window.fluentEventFcomPortal', $html);
        $this->assertStringContainsString('<script type="module"', $html);
        $this->assertStringContainsString('id="fluent_event_fcom_portal_css"', $html);
    }

    private function handler($fcomLoadsAssets)
    {
        return new class($fcomLoadsAssets) extends PortalHandler {
            private $loads;

            public function __construct($loads)
            {
                $this->loads = $loads;
            }

            protected function fcomLoadsAssets()
            {
                return $this->loads;
            }
        };
    }

    private function printed(PortalHandler $handler)
    {
        ob_start();
        $handler->printAssets();

        return ob_get_clean();
    }
}
