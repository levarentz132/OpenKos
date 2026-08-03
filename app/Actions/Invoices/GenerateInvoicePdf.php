<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\Setting;
use Dompdf\Dompdf;
use Dompdf\Options;

final class GenerateInvoicePdf
{
    public function execute(Invoice $invoice): string
    {
        $settings = Setting::some(['site_name', 'locale', 'currency']);
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('invoices.pdf', [
            'currency' => $settings['currency'] ?? 'IDR',
            'invoice' => $invoice,
            'locale' => $settings['locale'] ?? 'id',
            'siteName' => $settings['site_name'] ?? config('app.name'),
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
