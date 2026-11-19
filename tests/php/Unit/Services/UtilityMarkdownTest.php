<?php

namespace FluentEvent\Tests\Unit\Services;

use FluentEvent\App\Utils\Utility;
use FluentEvent\Tests\TestCase;

/**
 * The portal's description comes from fcom's editor, which saves each empty line as a lone "<br />".
 */
class UtilityMarkdownTest extends TestCase
{
    public function test_empty_lines_from_the_editor_render_as_line_breaks()
    {
        $html = Utility::mdToHtml("First line\n\n<br />\n\n<br />\n\n<br />\n\nSecond line");

        $this->assertStringNotContainsString('&lt;br', $html);
        $this->assertSame(3, substr_count($html, '<p><br /></p>'));
        $this->assertStringContainsString('<p>First line</p>', $html);
        $this->assertStringContainsString('<p>Second line</p>', $html);
    }

    public function test_html_typed_in_a_line_is_still_escaped()
    {
        $html = Utility::mdToHtml('Text <br /> and <script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;br /&gt;', $html);
    }
}
