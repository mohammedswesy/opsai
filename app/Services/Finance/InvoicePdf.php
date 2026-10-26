<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Organization;
use App\Support\LocaleFormat;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Renders an invoice as a PDF with mPDF. mPDF shapes Arabic (joined letter forms
 * through its OpenType layout engine) and lays out right-to-left text; its bundled
 * DejaVu Sans covers Latin and Arabic, and XB Riyaz is used for Arabic runs.
 */
class InvoicePdf
{
    public function render(Invoice $invoice, Organization $organization, string $locale): string
    {
        $invoice->loadMissing(['customer', 'branch:id,name', 'items.service:id,name', 'payments']);
        $rtl = $locale === 'ar';
        $format = new LocaleFormat($locale, $organization->timezone ?: 'Asia/Riyadh');

        $html = view('pdf.invoice', [
            'invoice' => $invoice,
            'organization' => $organization,
            'format' => $format,
            'rtl' => $rtl,
        ])->render();

        $tempDir = storage_path('framework/cache/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 16,
            'margin_bottom' => 16,
        ]);
        $mpdf->SetDirectionality($rtl ? 'rtl' : 'ltr');
        $mpdf->SetTitle($invoice->invoice_number);
        $mpdf->SetCreator(config('app.name', 'OpsAI'));
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    public function filename(Invoice $invoice): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoice->invoice_number) ?: 'invoice';

        return trim($name, '-').'.pdf';
    }
}
