<?php

namespace Modules\Core\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Modules\Core\Enums\ReportTemplateType;
use Modules\Core\Support\PDF\PDFFactory;
use Modules\Invoices\Models\Invoice;
use Modules\Quotes\Models\Quote;
use RuntimeException;
use Throwable;

/**
 * Turns an Invoice or Quote into a PDF via its report template.
 *
 * Template resolution chain: the document's own template slug, then the
 * company default (companies.invoice_template / companies.quote_template),
 * then the shipped system default. Slugs are looked up in the company
 * scope first so clones shadow system templates of the same name.
 */
class PdfGenerationService
{
    public function __construct(
        protected ReportTemplateStorage $storage,
        protected ReportRenderer $renderer,
        protected ReportDataMapper $mapper,
    ) {}

    public function renderInvoiceHtml(Invoice $invoice): string
    {
        return $this->renderer->render(
            $this->resolveTemplate($invoice),
            $this->mapper->forInvoice($invoice),
        );
    }

    public function renderQuoteHtml(Quote $quote): string
    {
        return $this->renderer->render(
            $this->resolveTemplate($quote),
            $this->mapper->forQuote($quote),
        );
    }

    public function invoicePdf(Invoice $invoice): string
    {
        return PDFFactory::create()->getOutput($this->renderInvoiceHtml($invoice));
    }

    public function quotePdf(Quote $quote): string
    {
        return PDFFactory::create()->getOutput($this->renderQuoteHtml($quote));
    }

    public function downloadInvoice(Invoice $invoice): Response
    {
        return response($this->invoicePdf($invoice))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $this->filename('invoice', (string) ($invoice->invoice_number ?: $invoice->id)) . '"');
    }

    public function downloadQuote(Quote $quote): Response
    {
        return response($this->quotePdf($quote))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $this->filename('quote', (string) ($quote->quote_number ?: $quote->id)) . '"');
    }

    /**
     * @return array{manifest: array, bands: array<string, array>}
     */
    public function resolveTemplate(Invoice|Quote $document): array
    {
        $type = $document instanceof Invoice ? ReportTemplateType::INVOICE : ReportTemplateType::QUOTE;

        $companyDefault = $document instanceof Invoice
            ? $document->company?->invoice_template
            : $document->company?->quote_template;

        foreach (array_filter([(string) $document->template, (string) $companyDefault, 'default']) as $slug) {
            if (($template = $this->loadBySlug($slug, $type)) !== null) {
                return $template;
            }
        }

        throw new RuntimeException(
            "No report template found for {$type->value} documents. Run \"php artisan reports:sync-system\".",
        );
    }

    protected function loadBySlug(string $slug, ReportTemplateType $type): ?array
    {
        try {
            $template = $this->storage->load(ReportTemplateStorage::SCOPE_COMPANY, $slug);
        } catch (Throwable) {
            $template = null;
        }

        if ($template !== null && ($template['manifest']['type'] ?? null) === $type->value) {
            return $template;
        }

        try {
            $template = $this->storage->load(ReportTemplateStorage::SCOPE_SYSTEM, $slug, $type);
            if ($template !== null) {
                return $template;
            }
        } catch (Throwable) {
            // fall through to resource fallback
        }

        return $this->loadFromResources($slug, $type);
    }

    protected function loadFromResources(string $slug, ReportTemplateType $type): ?array
    {
        if ($slug === '' || preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) !== 1) {
            return null;
        }

        $base = resource_path("report-templates/{$type->value}/{$slug}");
        $manifestPath = $base . '/manifest.json';
        $bandsPath = $base . '/bands.json';

        if (! File::exists($manifestPath)) {
            return null;
        }

        try {
            $manifest = json_decode((string) File::get($manifestPath), true, 64, JSON_THROW_ON_ERROR);

            if (! is_array($manifest)) {
                return null;
            }

            $bands = [];
            if (File::exists($bandsPath)) {
                $decodedBands = json_decode((string) File::get($bandsPath), true, 64, JSON_THROW_ON_ERROR);
                if (is_array($decodedBands)) {
                    $bands = $decodedBands;
                }
            }

            return [
                'manifest' => $this->storage->sanitizeManifest($manifest),
                'bands'    => $this->storage->sanitizeBands($bands, $type),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function filename(string $prefix, string $number): string
    {
        $number = preg_replace('/[^A-Za-z0-9\-_]/', '-', $number) ?: 'document';

        return $prefix . '-' . $number . '.pdf';
    }
}
