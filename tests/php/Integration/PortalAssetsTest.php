<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Utils\Enqueuer\Enqueue;
use FluentEvent\Modules\FluentCommunity\PortalHandler;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * fcom's add-on module runtime loads the portal script and stylesheet; we print only the vars.
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

    public function test_our_stylesheet_comes_after_fcoms()
    {
        $vars = (new PortalHandler())->addStylesheet(['css_files' => ['fcom_global' => ['url' => 'global.css']]]);

        $this->assertSame('fluent_event_fcom_portal', array_key_last($vars['css_files']));
        $this->assertSame(Enqueue::url('fcom-portal/fcom-portal.scss'), $vars['css_files']['fluent_event_fcom_portal']['url']);
    }

    public function test_we_print_only_the_vars()
    {
        ob_start();
        (new PortalHandler())->printVars();
        $html = ob_get_clean();

        $this->assertStringContainsString('window.fluentEventFcomPortal', $html);
        $this->assertStringNotContainsString('<script type="module"', $html);
        $this->assertStringNotContainsString('<link', $html);
    }
}
