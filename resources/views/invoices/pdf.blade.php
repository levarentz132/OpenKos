<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->reference ?? $invoice->getKey() }}</title>
    <style>
        @page {
            margin: 24px 28px 24px 28px;
            size: a4 portrait;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            background: #ffffff;
            margin: 0;
            padding: 0;
        }
        body {
            color: #1e293b;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5px;
            line-height: 1.45;
        }

        /* Typography */
        h1, h2, h3, h4, p { margin: 0; padding: 0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-muted { color: #64748b; }
        .text-slate { color: #334155; }
        .text-dark { color: #0f172a; }
        .font-bold { font-weight: 700; }
        .font-extrabold { font-weight: 800; }
        .font-semibold { font-weight: 600; }
        .uppercase { text-transform: uppercase; }
        .tabular { font-variant-numeric: tabular-nums; }

        /* Tables */
        table {
            border-collapse: collapse;
            width: 100%;
        }
        tr {
            page-break-inside: avoid;
        }

        /* Luxury Top Hero Banner */
        .hero-banner {
            background-color: #0f172a;
            border-radius: 8px;
            color: #ffffff;
            margin-bottom: 14px;
            padding: 20px 24px;
        }
        .hero-banner td {
            vertical-align: middle;
        }
        .hero-brand {
            color: #ffffff;
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 2px;
            line-height: 1.1;
            text-transform: uppercase;
        }
        .hero-tagline {
            color: #38bdf8;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 1.2px;
            margin-top: 4px;
            text-transform: uppercase;
        }
        .hero-property {
            color: #cbd5e1;
            font-size: 9.5px;
            margin-top: 6px;
        }
        .hero-title {
            color: #ffffff;
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 2.5px;
            line-height: 1;
            text-transform: uppercase;
        }
        .hero-ref-pill {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 4px;
            color: #f8fafc;
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.8px;
            margin-top: 6px;
            padding: 3px 10px;
        }

        /* Status Badges */
        .status-badge-lg {
            border-radius: 20px;
            display: inline-block;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.8px;
            margin-top: 6px;
            padding: 4px 14px;
            text-transform: uppercase;
        }
        .badge-paid {
            background-color: #10b981;
            border: 1px solid #059669;
            color: #ffffff;
        }
        .badge-pending {
            background-color: #f59e0b;
            border: 1px solid #d97706;
            color: #ffffff;
        }
        .badge-overdue {
            background-color: #ef4444;
            border: 1px solid #dc2626;
            color: #ffffff;
        }
        .badge-partial {
            background-color: #6366f1;
            border: 1px solid #4f46e5;
            color: #ffffff;
        }

        /* 3-Column Key Info Ribbon */
        .meta-ribbon {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 16px;
            width: 100%;
        }
        .meta-ribbon td {
            padding: 12px 16px;
            vertical-align: top;
            width: 33.33%;
        }
        .meta-ribbon td + td {
            border-left: 1px solid #e2e8f0;
        }
        .ribbon-label {
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.8px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .ribbon-title {
            color: #0f172a;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .ribbon-item {
            color: #475569;
            font-size: 9px;
            line-height: 1.45;
        }
        .ribbon-item strong {
            color: #0f172a;
        }

        /* Items Section */
        .section-header {
            border-bottom: 1.5px solid #0f172a;
            color: #0f172a;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
            padding-bottom: 4px;
            text-transform: uppercase;
        }
        .charges-table {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 16px;
            overflow: hidden;
            width: 100%;
        }
        .charges-table th {
            background-color: #0f172a;
            color: #f8fafc;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.8px;
            padding: 9px 12px;
            text-align: left;
            text-transform: uppercase;
        }
        .charges-table th.text-right {
            text-align: right;
        }
        .charges-table td {
            border-bottom: 1px solid #f1f5f9;
            font-size: 9.5px;
            padding: 10px 12px;
            vertical-align: middle;
        }
        .charges-table tr:nth-child(even) td {
            background-color: #fafbfc;
        }
        .charges-table tr:last-child td {
            border-bottom: none;
        }
        .charge-title {
            color: #0f172a;
            font-size: 10px;
            font-weight: 700;
        }
        .charge-subtitle {
            color: #64748b;
            font-size: 8.5px;
            margin-top: 2px;
        }
        .tag-pill {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            color: #475569;
            display: inline-block;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.4px;
            padding: 2px 7px;
            text-transform: uppercase;
        }

        /* Settlement & Summary Split Area */
        .split-table {
            margin-bottom: 16px;
            width: 100%;
        }
        .split-table td {
            vertical-align: top;
        }
        .left-col {
            padding-right: 10px;
            width: 54%;
        }
        .right-col {
            padding-left: 10px;
            width: 46%;
        }

        /* Payment Seal Card */
        .verification-card {
            background: #f8fafc;
            border: 1.5px dashed #cbd5e1;
            border-radius: 8px;
            padding: 12px 14px;
        }
        .card-title {
            color: #0f172a;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.6px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .seal-pill {
            background-color: #ecfdf5;
            border: 1.5px solid #10b981;
            border-radius: 6px;
            color: #047857;
            display: inline-block;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.6px;
            margin-bottom: 8px;
            padding: 4px 10px;
            text-transform: uppercase;
        }
        .receipt-row {
            color: #475569;
            font-size: 8.5px;
            line-height: 1.55;
        }
        .receipt-row strong {
            color: #0f172a;
        }

        /* Totals Card */
        .totals-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .totals-inner-table td {
            font-size: 9.5px;
            padding: 6px 14px;
        }
        .totals-inner-table .row-border td {
            border-top: 1px solid #f1f5f9;
        }
        .totals-key {
            color: #64748b;
            font-weight: 600;
        }
        .totals-val {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }
        .balance-highlight-box {
            background-color: #f0fdf4;
            border-top: 2px solid #10b981;
            padding: 10px 14px;
        }
        .balance-due-box {
            background-color: #fef2f2;
            border-top: 2px solid #ef4444;
            padding: 10px 14px;
        }
        .balance-label {
            color: #0f172a;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .balance-amount {
            color: #047857;
            font-size: 16px;
            font-weight: 900;
            margin-top: 2px;
            text-align: right;
        }
        .balance-amount-due {
            color: #b91c1c;
            font-size: 16px;
            font-weight: 900;
            margin-top: 2px;
            text-align: right;
        }

        /* Payments Ledger Table */
        .payments-table {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 14px;
            width: 100%;
        }
        .payments-table th {
            background-color: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            color: #475569;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 6px 10px;
            text-align: left;
            text-transform: uppercase;
        }
        .payments-table td {
            border-bottom: 1px solid #f8fafc;
            font-size: 8.5px;
            padding: 7px 10px;
        }
        .payments-table tr:last-child td {
            border-bottom: none;
        }

        /* Elegant Footer */
        .footer-wrap {
            border-top: 1.5px solid #e2e8f0;
            margin-top: 18px;
            padding-top: 12px;
            width: 100%;
        }
        .footer-wrap td {
            color: #94a3b8;
            font-size: 8px;
            line-height: 1.4;
            vertical-align: top;
        }
        .footer-wrap strong {
            color: #475569;
        }
    </style>
</head>
<body>
@php
    $formatDate = static fn ($date): string => $date?->copy()->locale($locale)->translatedFormat('d M Y') ?? '-';
    $formatDateTime = static fn ($date): string => $date?->copy()->timezone('Asia/Jakarta')->locale($locale)->translatedFormat('d M Y, H:i') ?? '-';
    $formatMoney = static function ($amount) use ($currency, $locale): string {
        if (extension_loaded('intl')) {
            try {
                return (string) Illuminate\Support\Number::currency(
                    (float) $amount,
                    in: $currency,
                    locale: $locale,
                    precision: 2,
                );
            } catch (\Throwable) {
                // Fallback below
            }
        }

        $num = (float) $amount;
        $formatted = number_format($num, 2, ',', '.');
        $prefix = match ($currency) {
            'IDR' => 'Rp',
            'USD' => '$',
            'EUR' => '€',
            default => $currency,
        };

        return trim($prefix . ' ' . $formatted);
    };

    $property = $invoice->lease?->unit?->property;
    $propertyAddress = collect([
        $property?->address,
        $property?->city?->name,
        $property?->region?->name,
        $property?->postal_code,
    ])->filter()->implode(', ');

    $rawStatus = $invoice->display_status ?? $invoice->status?->value ?? 'pending';
    $statusBadgeClass = match ($rawStatus) {
        'paid' => 'badge-paid',
        'partial' => 'badge-partial',
        'overdue' => 'badge-overdue',
        default => 'badge-pending',
    };
    $statusText = match ($rawStatus) {
        'paid' => '✓ PAID IN FULL',
        'partial' => 'PARTIALLY PAID',
        'overdue' => '⚠ OVERDUE',
        'cancelled' => 'CANCELLED',
        'void' => 'VOID',
        default => 'PAYMENT PENDING',
    };

    $tenant = $invoice->lease?->primaryTenant;
    $payments = $invoice->payments ?? collect();
    $generatedAt = now()->timezone('Asia/Jakarta')->locale($locale)->translatedFormat('d M Y, H:i');
    $isPaidInFull = (float) $invoice->outstanding <= 0;
    $latestPayment = $payments->last();
@endphp

<!-- Hero Banner Header -->
<table class="hero-banner">
    <tr>
        <td style="width: 58%;">
            <div class="hero-brand">{{ $siteName }}</div>
            <div class="hero-tagline">Smart Property Concierge & Residency</div>
            <div class="hero-property">
                <strong>{{ $property?->name ?? 'HighlanderStay Residence' }}</strong>
                @if ($invoice->lease?->unit?->name)
                    &nbsp;&bull;&nbsp; Unit {{ $invoice->lease->unit->name }}
                @endif
                @if ($propertyAddress !== '')
                    <br><span style="color: #94a3b8; font-size: 8px;">{{ $propertyAddress }}</span>
                @endif
            </div>
        </td>
        <td class="text-right" style="width: 42%;">
            <div class="hero-title">INVOICE</div>
            <div>
                <span class="hero-ref-pill">{{ $invoice->reference ?? '#INV-'.$invoice->getKey() }}</span>
            </div>
            <div>
                <span class="status-badge-lg {{ $statusBadgeClass }}">{{ $statusText }}</span>
            </div>
        </td>
    </tr>
</table>

<!-- 3-Column Metadata Ribbon -->
<table class="meta-ribbon">
    <tr>
        <!-- Column 1: Billed To -->
        <td>
            <div class="ribbon-label">Billed Resident</div>
            <div class="ribbon-title">{{ $tenant?->name ?? 'Resident' }}</div>
            <div class="ribbon-item">
                @if ($tenant?->phone)
                    Phone: <strong>{{ $tenant->phone }}</strong><br>
                @endif
                @if ($tenant?->user?->email)
                    Email: {{ $tenant->user->email }}<br>
                @endif
                Tenant ID: #{{ $tenant?->getKey() ?? '-' }}
            </div>
        </td>

        <!-- Column 2: Lease Details -->
        <td>
            <div class="ribbon-label">Lease & Premises</div>
            <div class="ribbon-title">
                Unit {{ $invoice->lease?->unit?->name ?? '-' }}
            </div>
            <div class="ribbon-item">
                Property: <strong>{{ $property?->name ?? '-' }}</strong><br>
                Lease Agreement: <strong>{{ $invoice->lease?->reference ?? '-' }}</strong><br>
                Type: Residential Rental
            </div>
        </td>

        <!-- Column 3: Billing Schedule -->
        <td>
            <div class="ribbon-label">Billing Schedule</div>
            <div class="ribbon-item" style="margin-top: 3px;">
                Issue Date: <strong>{{ $formatDate($invoice->created_at) }}</strong><br>
                Billing Period:<br>
                <strong>{{ $formatDate($invoice->period_start) }} &ndash; {{ $formatDate($invoice->period_end) }}</strong><br>
                Due Date: <strong style="{{ $rawStatus === 'overdue' ? 'color: #dc2626;' : '' }}">{{ $formatDate($invoice->due_date) }}</strong>
            </div>
        </td>
    </tr>
</table>

<!-- Itemized Charges -->
<div class="section-header">Itemized Rental Charges</div>
<table class="charges-table">
    <thead>
        <tr>
            <th style="width: 52%;">Description</th>
            <th style="width: 20%;">Category</th>
            <th class="text-right" style="width: 28%;">Total Amount</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($invoice->lineItems as $item)
        <tr>
            <td>
                <div class="charge-title">{{ $item->description }}</div>
                <div class="charge-subtitle">
                    Period: {{ $formatDate($invoice->period_start) }} to {{ $formatDate($invoice->period_end) }}
                </div>
            </td>
            <td>
                <span class="tag-pill">{{ str($item->type)->replace('_', ' ') }}</span>
            </td>
            <td class="text-right font-extrabold tabular" style="color: #0f172a; font-size: 10.5px;">
                {{ $formatMoney($item->amount) }}
            </td>
        </tr>
    @empty
        <tr>
            <td class="text-center text-muted" colspan="3" style="padding: 16px;">
                No itemized charges listed for this billing period.
            </td>
        </tr>
    @endforelse
    </tbody>
</table>

<!-- Settlement & Balance Summary Split -->
<table class="split-table">
    <tr>
        <!-- Left: Payment Verification & Settlement Seal -->
        <td class="left-col">
            <div class="verification-card">
                <div class="card-title">Settlement Verification</div>
                @if ($isPaidInFull)
                    <div class="seal-pill">
                        &check; Confirmed &amp; Reconciled
                    </div>
                    <div class="receipt-row">
                        @if ($latestPayment)
                            Method: <strong>{{ str($latestPayment->payment_method)->replace('_', ' ')->title() }}</strong><br>
                            Reference: <strong>{{ $latestPayment->reference_number ?? 'Verified Record' }}</strong><br>
                            Settled on: <strong>{{ $formatDate($latestPayment->payment_date) }}</strong><br>
                        @endif
                        Status: <strong>Invoice fully settled. No payment required.</strong>
                    </div>
                @else
                    <div style="color: #b45309; font-weight: 700; margin-bottom: 6px;">
                        &bull; Pending Settlement
                    </div>
                    <div class="receipt-row">
                        Please settle the outstanding balance on or before the due date ({{ $formatDate($invoice->due_date) }}).
                        Bank transfer and online payments can be submitted via the tenant portal.
                    </div>
                @endif
            </div>

            @if ($payments->isNotEmpty())
                <div style="margin-top: 12px;">
                    <div class="card-title" style="margin-bottom: 6px;">Transaction History</div>
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Ref</th>
                                <th class="text-right">Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td>{{ $formatDate($payment->payment_date) }}</td>
                                <td><strong>{{ str($payment->payment_method)->replace('_', ' ')->title() }}</strong></td>
                                <td class="text-muted">{{ $payment->reference_number ?? '-' }}</td>
                                <td class="text-right font-bold tabular" style="color: #047857;">
                                    {{ $formatMoney($payment->amount) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </td>

        <!-- Right: Financial Statement Box -->
        <td class="right-col">
            <div class="totals-box">
                <table class="totals-inner-table">
                    <tr>
                        <td class="totals-key">Subtotal (Net Charges):</td>
                        <td class="totals-val tabular">{{ $formatMoney($invoice->total) }}</td>
                    </tr>
                    <tr class="row-border">
                        <td class="totals-key">Applicable Taxes / Fees:</td>
                        <td class="totals-val tabular">{{ $formatMoney('0') }}</td>
                    </tr>
                    <tr class="row-border" style="background: #fafbfc;">
                        <td class="totals-key" style="color: #0f172a; font-weight: 700;">Gross Invoice Total:</td>
                        <td class="totals-val tabular" style="font-size: 11px;">{{ $formatMoney($invoice->total) }}</td>
                    </tr>
                    <tr class="row-border">
                        <td class="totals-key" style="color: #047857;">Total Payments Received:</td>
                        <td class="totals-val tabular" style="color: #047857; font-size: 11px;">
                            &minus; {{ $formatMoney($invoice->amount_paid) }}
                        </td>
                    </tr>
                </table>

                <div class="{{ $isPaidInFull ? 'balance-highlight-box' : 'balance-due-box' }}">
                    <table style="width: 100%;">
                        <tr>
                            <td style="vertical-align: middle;">
                                <div class="balance-label">
                                    {{ $isPaidInFull ? 'Balance Due (Paid in Full)' : 'Balance Due (Outstanding)' }}
                                </div>
                                <div style="font-size: 7.5px; color: #64748b; margin-top: 1px;">
                                    {{ $isPaidInFull ? 'Zero balance remaining' : 'Payment due promptly' }}
                                </div>
                            </td>
                            <td class="text-right" style="vertical-align: middle;">
                                <div class="{{ $isPaidInFull ? 'balance-amount' : 'balance-amount-due' }} tabular">
                                    {{ $formatMoney($invoice->outstanding) }}
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </td>
    </tr>
</table>

<!-- Footer Note -->
<table class="footer-wrap">
    <tr>
        <td style="width: 55%;">
            <strong>{{ $siteName }} Property Management</strong><br>
            Thank you for staying with us. For inquiries or support, please contact your residence concierge.
        </td>
        <td class="text-right" style="width: 45%;">
            Generated: <strong>{{ $generatedAt }} WIB</strong><br>
            Official digital document &bull; Valid without physical stamp
        </td>
    </tr>
</table>

@if ($autoPrint ?? false)
    <script>
        window.addEventListener('load', () => window.print());
    </script>
@endif
</body>
</html>
