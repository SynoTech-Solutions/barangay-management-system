<?php

namespace Tests\Unit\Forms;

use App\Domain\Forms\FormSnapshot;
use App\Domain\Forms\Placeholder;
use App\Domain\Forms\SnapshotField;
use App\Domain\Forms\TemplateRenderer;
use App\Enums\FormInputType;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    public function test_values_are_html_escaped_so_markup_in_a_name_renders_inert(): void
    {
        $rendered = TemplateRenderer::render('<p>Name: {{resident.full_name}}</p>', ['resident.full_name' => '<script>alert(1)</script> & "Co"']);

        $this->assertSame('<p>Name: &lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;Co&quot;</p>', $rendered->html);
        $this->assertStringNotContainsString('<script>', $rendered->html);
        $this->assertSame([], $rendered->missing);
    }

    public function test_missing_tokens_stay_visible_and_are_reported(): void
    {
        $rendered = TemplateRenderer::render('<p>{{ resident.full_name }} {{field.purpose}}</p>', ['resident.full_name' => 'Ana']);

        $this->assertSame('<p>Ana {{field.purpose}}</p>', $rendered->html);
        $this->assertSame(['field.purpose'], $rendered->missing);
    }

    public function test_values_that_contain_placeholders_are_not_expanded_and_line_breaks_are_kept(): void
    {
        $rendered = TemplateRenderer::render('<p>{{field.remarks}}</p>', [
            'field.remarks' => "First {{resident.full_name}}\nSecond",
            'resident.full_name' => 'Should not appear',
        ]);

        $this->assertSame('<p>First {{resident.full_name}}<br>Second</p>', $rendered->html);
    }

    public function test_tokens_are_listed_and_unknown_ones_are_found(): void
    {
        $body = '<p>{{resident.full_name}} {{field.purpose}} {{field.nope}} {{resident.color}} {{resident.full_name}}</p>';

        $this->assertSame(['resident.full_name', 'field.purpose', 'field.nope', 'resident.color'], Placeholder::tokensIn($body));
        $this->assertSame(['field.nope', 'resident.color'], Placeholder::unknownTokens($body, ['purpose']));
    }

    public function test_snapshot_values_render_with_document_tokens_and_round_trip_as_version_one(): void
    {
        $snapshot = new FormSnapshot(
            [new SnapshotField('purpose', 'Purpose', FormInputType::Text, 'Employment', 'Employment')],
            ['resident.full_name' => 'Juana Dela Cruz'],
        );
        $stored = $snapshot->toArray();
        $restored = FormSnapshot::fromArray($stored);

        $this->assertSame(1, $stored['v']);
        $this->assertSame(
            '<p>Juana Dela Cruz needs this for Employment (FX-1).</p>',
            TemplateRenderer::render('<p>{{resident.full_name}} needs this for {{field.purpose}} ({{document.number}}).</p>', $restored->placeholderValues(['document.number' => 'FX-1']))->html,
        );
    }
}
