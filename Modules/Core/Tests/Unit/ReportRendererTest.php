<?php

namespace Modules\Core\Tests\Unit;

use Modules\Core\Services\ReportRenderer;
use Modules\Core\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\Test;

class ReportRendererTest extends AbstractTestCase
{
    protected ReportRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new ReportRenderer();
    }

    #[Test]
    public function it_renders_configured_bricks_with_entity_data(): void
    {
        /* Act */
        $html = $this->renderer->render($this->template([
            'header'  => [['brick' => 'header_company', 'width' => 'half', 'config' => []]],
            'details' => [['brick' => 'detail_items', 'width' => 'full', 'config' => []]],
        ]), $this->data());

        /* Assert */
        $this->assertStringContainsString('ACME Corp', $html);
        $this->assertStringContainsString('Widget', $html);
        $this->assertStringContainsString('report-band-header', $html);
        $this->assertStringContainsString('report-band-details', $html);
    }

    #[Test]
    public function it_omits_fields_toggled_off_in_the_brick_config(): void
    {
        /* Act */
        $html = $this->renderer->render($this->template([
            'header' => [['brick' => 'header_company', 'width' => 'full', 'config' => ['show_vat_id' => false]]],
        ]), $this->data());

        /* Assert */
        $this->assertStringContainsString('ACME Corp', $html);
        $this->assertStringNotContainsString('VAT-123', $html);
    }

    #[Test]
    public function it_skips_bands_without_entries_and_unknown_bricks(): void
    {
        /* Act */
        $html = $this->renderer->render($this->template([
            'header' => [['brick' => 'nonexistent_brick', 'width' => 'full', 'config' => []]],
        ]), $this->data());

        /* Assert */
        $this->assertStringNotContainsString('report-band-details', $html);
        $this->assertStringNotContainsString('nonexistent', $html);
    }

    #[Test]
    public function it_marks_keep_together_bands_with_page_break_css(): void
    {
        /* Act */
        $html = $this->renderer->render($this->template(
            ['footer' => [['brick' => 'footer_totals', 'width' => 'full', 'config' => []]]],
            ['band_options' => ['footer' => ['keep_together' => true]]],
        ), $this->data());

        /* Assert */
        $this->assertStringContainsString('page-break-inside: avoid', $html);
    }

    #[Test]
    public function it_renders_manual_page_breaks_and_spacers(): void
    {
        /* Act */
        $html = $this->renderer->render($this->template([
            'details' => [
                ['brick' => 'page_break', 'width' => 'full', 'config' => []],
                ['brick' => 'spacer', 'width' => 'full', 'config' => ['height' => 55]],
            ],
        ]), $this->data());

        /* Assert */
        $this->assertStringContainsString('page-break-after: always', $html);
        $this->assertStringContainsString('height: 55px', $html);
    }

    #[Test]
    public function it_applies_block_widths_as_percentages(): void
    {
        /* Act */
        $html = $this->renderer->render($this->template([
            'header' => [['brick' => 'header_company', 'width' => 'half', 'config' => []]],
        ]), $this->data());

        /* Assert */
        $this->assertStringContainsString('width: 50%', $html);
    }

    #[Test]
    public function it_renders_grouped_bands_repeating_per_distinct_group_value_in_first_seen_order(): void
    {
        /* Arrange */
        $template = $this->template([
            'header'       => [['brick' => 'header_company', 'width' => 'full', 'config' => []]],
            'group_header' => [['brick' => 'detail_column_labels', 'width' => 'full', 'config' => []]],
            'details'      => [['brick' => 'detail_items', 'width' => 'full', 'config' => []]],
            'group_footer' => [['brick' => 'footer_totals', 'width' => 'full', 'config' => []]],
            'footer'       => [['brick' => 'footer_notes', 'width' => 'full', 'config' => ['footer_content' => '<p>Doc Footer</p>']]],
        ], [
            'band_options' => [
                'details' => ['group_by' => 'category'],
            ],
        ]);

        $data          = $this->data();
        $data['items'] = [
            ['description' => 'Consulting 1', 'category' => 'Services', 'quantity' => 1, 'price' => '100.00', 'tax' => '20.00', 'total' => '120.00'],
            ['description' => 'Laptop', 'category' => 'Hardware', 'quantity' => 2, 'price' => '500.00', 'tax' => '100.00', 'total' => '1100.00'],
            ['description' => 'Consulting 2', 'category' => 'Services', 'quantity' => 3, 'price' => '100.00', 'tax' => '30.00', 'total' => '330.00'],
        ];

        /* Act */
        $html = $this->renderer->render($template, $data);

        /* Assert */
        $this->assertSame(2, mb_substr_count($html, 'class="report-group"'), 'Expected exactly 2 group repetitions.');
        $this->assertSame(2, mb_substr_count($html, 'report-band-group_header'));
        $this->assertSame(2, mb_substr_count($html, 'report-band-details'));
        $this->assertSame(2, mb_substr_count($html, 'report-band-group_footer'));
        $this->assertSame(1, mb_substr_count($html, 'report-band-header'));
        $this->assertSame(1, mb_substr_count($html, 'report-band-footer'));

        // First group (Services) comes before second group (Hardware)
        $servicesPos = mb_strpos($html, 'Consulting 1');
        $hardwarePos = mb_strpos($html, 'Laptop');
        $this->assertNotFalse($servicesPos);
        $this->assertNotFalse($hardwarePos);
        $this->assertLessThan($hardwarePos, $servicesPos, 'Group order must match first-seen item order (Services before Hardware).');

        // Per-group totals
        // Services group: subtotal = 100 + 300 = 400.00, tax = 20 + 30 = 50.00, total = 120 + 330 = 450.00
        $this->assertStringContainsString('400.00', $html);
        $this->assertStringContainsString('450.00', $html);

        // Hardware group: subtotal = 1000.00, tax = 100.00, total = 1100.00
        $this->assertStringContainsString('1000.00', $html);
        $this->assertStringContainsString('1100.00', $html);
    }

    #[Test]
    public function it_preserves_first_seen_group_order_without_resorting(): void
    {
        /* Arrange */
        $template = $this->template([
            'group_header' => [['brick' => 'detail_column_labels', 'width' => 'full', 'config' => []]],
            'details'      => [['brick' => 'detail_items', 'width' => 'full', 'config' => []]],
        ], [
            'band_options' => [
                'details' => ['group_by' => 'category'],
            ],
        ]);

        $data          = $this->data();
        $data['items'] = [
            ['description' => 'Zeta Item', 'category' => 'Zeta', 'quantity' => 1, 'price' => '10.00', 'tax' => '0.00', 'total' => '10.00'],
            ['description' => 'Alpha Item', 'category' => 'Alpha', 'quantity' => 1, 'price' => '20.00', 'tax' => '0.00', 'total' => '20.00'],
            ['description' => 'Beta Item', 'category' => 'Beta', 'quantity' => 1, 'price' => '30.00', 'tax' => '0.00', 'total' => '30.00'],
        ];

        /* Act */
        $html = $this->renderer->render($template, $data);

        /* Assert */
        $zetaPos  = mb_strpos($html, 'Zeta Item');
        $alphaPos = mb_strpos($html, 'Alpha Item');
        $betaPos  = mb_strpos($html, 'Beta Item');

        $this->assertNotFalse($zetaPos);
        $this->assertNotFalse($alphaPos);
        $this->assertNotFalse($betaPos);
        $this->assertLessThan($alphaPos, $zetaPos, 'Zeta must come before Alpha in first-seen order.');
        $this->assertLessThan($betaPos, $alphaPos, 'Alpha must come before Beta in first-seen order.');
    }

    #[Test]
    public function it_applies_keep_together_to_grouped_repetitions(): void
    {
        /* Arrange */
        $template = $this->template([
            'details' => [['brick' => 'detail_items', 'width' => 'full', 'config' => []]],
        ], [
            'band_options' => [
                'details' => ['group_by' => 'category', 'keep_together' => true],
            ],
        ]);

        $data          = $this->data();
        $data['items'] = [
            ['description' => 'Item 1', 'category' => 'A', 'quantity' => 1, 'price' => '10.00', 'tax' => '0.00', 'total' => '10.00'],
        ];

        /* Act */
        $html = $this->renderer->render($template, $data);

        /* Assert */
        $this->assertStringContainsString('class="report-group" style="page-break-inside: avoid;"', $html);
    }

    #[Test]
    public function it_handles_empty_items_cleanly_when_group_by_is_configured(): void
    {
        /* Arrange */
        $template = $this->template([
            'header'       => [['brick' => 'header_company', 'width' => 'full', 'config' => []]],
            'group_header' => [['brick' => 'detail_column_labels', 'width' => 'full', 'config' => []]],
            'details'      => [['brick' => 'detail_items', 'width' => 'full', 'config' => []]],
            'footer'       => [['brick' => 'footer_totals', 'width' => 'full', 'config' => []]],
        ], [
            'band_options' => [
                'details' => ['group_by' => 'category'],
            ],
        ]);

        $data          = $this->data();
        $data['items'] = [];

        /* Act */
        $html = $this->renderer->render($template, $data);

        /* Assert */
        $this->assertStringContainsString('report-band-header', $html);
        $this->assertStringContainsString('report-band-footer', $html);
        $this->assertStringNotContainsString('class="report-group"', $html);
    }

    protected function template(array $bands, array $manifest = []): array
    {
        return [
            'manifest' => array_merge(['name' => 'Test', 'slug' => 'test', 'type' => 'invoice'], $manifest),
            'bands'    => array_merge(
                ['header' => [], 'group_header' => [], 'details' => [], 'group_footer' => [], 'footer' => []],
                $bands,
            ),
        ];
    }

    protected function data(): array
    {
        return [
            'company' => ['name' => 'ACME Corp', 'vat_id' => 'VAT-123', 'address' => 'Main Street 1'],
            'client'  => ['name' => 'Client Co'],
            'invoice' => ['number' => 'INV-001', 'date' => '2026-01-01', 'due_date' => '2026-02-01'],
            'items'   => [['description' => 'Widget', 'quantity' => 2, 'price' => '10.00', 'tax' => '4.00', 'total' => '24.00']],
            'totals'  => ['subtotal' => '20.00', 'tax' => '4.00', 'total' => '24.00', 'paid' => '0.00', 'balance' => '24.00'],
        ];
    }
}
