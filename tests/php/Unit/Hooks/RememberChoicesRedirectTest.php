<?php

namespace FluentEvent\Tests\Unit\Hooks;

use Brain\Monkey\Functions;
use FluentEvent\App\Hooks\Handlers\FrontEndHandler;
use FluentEvent\Tests\TestCase;

class RememberChoicesRedirectTest extends TestCase
{
    public function test_restoring_a_saved_view_stays_on_the_same_origin_when_the_path_starts_with_two_slashes()
    {
        $node = trim((string) shell_exec('command -v node'));
        if (!$node) {
            $this->markTestSkipped('Needs node to run the inline script.');
        }

        Functions\when('wp_get_inline_script_tag')->returnArg();

        $method = new \ReflectionMethod(FrontEndHandler::class, 'rememberChoices');
        $method->setAccessible(true);
        $script = $method->invoke(new FrontEndHandler(), 'cards', '');

        $harness = 'var href = "https://site.test//elsewhere.test/events";'
            . 'var target = null;'
            . 'var location = { href: href, pathname: new URL(href).pathname, search: "", hash: "",'
            . ' replace: function (url) { target = new URL(url, href); } };'
            . 'var localStorage = { getItem: function () { return "timeline"; }, setItem: function () {} };'
            . $script
            . ';process.stdout.write(target ? target.origin : "none");';

        $file = tempnam(sys_get_temp_dir(), 'fe-choices');
        file_put_contents($file, $harness);
        $origin = shell_exec(escapeshellarg($node) . ' ' . escapeshellarg($file));
        unlink($file);

        $this->assertSame('https://site.test', $origin);
    }
}
