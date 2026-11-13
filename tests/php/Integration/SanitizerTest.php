<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\App\Utils\CustomSanitizer;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Sanitizers against the real WordPress functions; a stub of esc_url_raw() would
 * only test the stub. No writes, but it shares the harness for consistency.
 */
class SanitizerTest extends IntegrationTestCase
{
    /**
     * @dataProvider urls
     */
    public function test_web_url_keeps_only_safe_web_links($input, $expected)
    {
        $this->assertSame($expected, CustomSanitizer::webUrl($input));
    }

    public function urls()
    {
        return [
            'https'                 => ['https://example.com/cover.jpg', 'https://example.com/cover.jpg'],
            'surrounding space'     => ['  https://example.com/a  ', 'https://example.com/a'],
            'javascript scheme'     => ['javascript:alert(1)', ''],
            'data scheme'           => ['data:text/html;base64,PHNjcmlwdD4=', ''],
            'non-ascii in the path' => ['https://example.com/Feature – 2.png', 'https://example.com/Feature%20%E2%80%93%202.png'],
            'idn host stays intact' => ['https://bücher.example/straße', 'https://bücher.example/stra%C3%9Fe'],
        ];
    }

    // A list would make sanitize_email() and the email rule throw; empty, it fails validation as missing.
    public function test_an_email_given_as_a_list_comes_out_empty()
    {
        $this->assertSame('', CustomSanitizer::sanitizeEmail(['visitor@example.test']));
        $this->assertSame('visitor@example.test', CustomSanitizer::sanitizeEmail(' visitor@example.test '));
    }

    public function test_an_event_request_is_sanitised_by_the_real_pipeline()
    {
        $data = EventRequest::sanitize([
            'calendar_id' => '5abc',
            'title'       => '<b>Launch</b> <script>alert(1)</script>',
            'description_md' => "Come along\r\n<script>alert(1)</script>",
            'cover_url'   => 'javascript:alert(1)',
            'address'     => "12 Road\nDhaka",
        ]);

        $this->assertSame(5, $data['calendar_id']);
        $this->assertSame('Launch', $data['title']);
        // Markdown is kept as written, line endings aside; it only reaches a page rendered and kses'd.
        $this->assertSame("Come along\n<script>alert(1)</script>", $data['description_md']);
        $this->assertArrayNotHasKey('description', $data);
        $this->assertSame('', $data['cover_url']);
        $this->assertSame("12 Road\nDhaka", $data['address'], 'A multi-line address must keep its line break');
    }
}
