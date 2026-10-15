<?php

namespace Tests\Unit\Forms;

use App\Domain\Forms\RichTextSanitizer;
use PHPUnit\Framework\TestCase;

class RichTextSanitizerTest extends TestCase
{
    public function test_scripts_and_their_content_are_removed(): void
    {
        $clean = RichTextSanitizer::clean('<p>Hello</p><script>alert("x")</script><style>p{color:red}</style><iframe src="https://example.invalid"></iframe>');

        $this->assertSame('<p>Hello</p>', $clean);
    }

    public function test_event_handler_attributes_are_dropped(): void
    {
        $clean = RichTextSanitizer::clean('<p onclick="steal()" onmouseover="x()" class="a" id="b">Text</p><img src="x" onerror="alert(1)">');

        $this->assertSame('<p>Text</p>', $clean);
    }

    public function test_javascript_urls_never_survive(): void
    {
        $clean = RichTextSanitizer::clean('<p><a href="javascript:alert(1)">Click</a> <span data-x="javascript:alert(1)">y</span></p>');

        $this->assertSame('<p>Click <span>y</span></p>', $clean);
        $this->assertStringNotContainsString('javascript', $clean);
    }

    public function test_style_expressions_are_dropped_and_only_alignment_is_kept(): void
    {
        $clean = RichTextSanitizer::clean('<p style="width: expression(alert(1))">A</p><p style="text-align: center;">B</p><div align="right">C</div><p style="text-align:center; background:url(javascript:x)">D</p>');

        $this->assertSame('<p>A</p><p style="text-align: center">B</p><div style="text-align: right">C</div><p>D</p>', $clean);
    }

    public function test_allowed_structure_and_placeholders_are_kept(): void
    {
        $html = '<h1>Title</h1><p><strong>Bold</strong> <em>it</em> <u>u</u><br>{{resident.full_name}}</p><ul><li>One</li></ul><table><tbody><tr><td colspan="2" rowspan="99">x</td></tr></tbody></table>';

        $this->assertSame(
            '<h1>Title</h1><p><strong>Bold</strong> <em>it</em> <u>u</u><br>{{resident.full_name}}</p><ul><li>One</li></ul><table><tbody><tr><td colspan="2">x</td></tr></tbody></table>',
            RichTextSanitizer::clean($html),
        );
    }

    public function test_unknown_wrappers_are_unwrapped_and_text_is_escaped(): void
    {
        $this->assertSame('<p>5 &lt; 6 &amp; done</p>', RichTextSanitizer::clean('<font face="x"><p>5 &lt; 6 &amp; done</p></font>'));
        $this->assertSame('', RichTextSanitizer::clean('   '));
    }
}
