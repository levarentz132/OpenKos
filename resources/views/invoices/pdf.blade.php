<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->reference ?? $invoice->getKey() }}</title>
    <style>
        @page {
            margin: 32px 36px 36px 36px;
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
            font-size: 10px;
            line-height: 1.45;
        }

        /* Typography */
        h1, h2, h3, p { margin: 0; padding: 0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-muted { color: #64748b; }
        .text-dark { color: #0f172a; }
        .font-bold { font-weight: 700; }
        .font-semibold { font-weight: 600; }
        .uppercase { text-transform: uppercase; }
        .tabular { font-variant-numeric: tabular-nums; }

        /* Tables & Layout */
        table {
            border-collapse: collapse;
            width: 100%;
        }
        tr {
            page-break-inside: avoid;
        }

        /* Top Brand Header */
        .header-table {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 18px;
            margin-bottom: 20px;
        }
        .header-table td {
            vertical-align: top;
        }
        .brand-title {
            color: #0f172a;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .brand-property {
            color: #2563eb;
            font-size: 11px;
            font-weight: 700;
            margin-top: 3px;
        }
        .brand-address {
            color: #64748b;
            font-size: 9px;
            line-height: 1.4;
            margin-top: 4px;
            max-width: 320px;
        }
        .invoice-title {
            color: #0f172a;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 1.5px;
            line-height: 1;
            text-transform: uppercase;
        }
        .invoice-ref {
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            margin-top: 5px;
        }

        /* Status Badge */
        .status-badge {
            border-radius: 12px;
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.6px;
            margin-top: 8px;
            padding: 4px 12px;
            text-transform: uppercase;
        }
        .status-paid {
            background-color: #dcfce7;
            border: 1px solid #86efac;
            color: #15803d;
        }
        .status-partial {
            background-color: #e0e7ff;
            border: 1px solid #a5b4fc;
            color: #4338ca;
        }
        .status-pending {
            background-color: #fef3c7;
            border: 1px solid #fcd34d;
            color: #b45309;
        }
        .status-overdue {
            background-color: #fee2e2;
            border: 1px solid #fca5a5;
            color: #b91c1c;
        }
        .status-other {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
        }

        /* Two Column Info Section */
        .info-card-table {
            margin-bottom: 22px;
        }
        .info-card-table td {
            vertical-align: top;
            width: 50%;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 14px;
        }
        .card-header {
            color: #64748b;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .tenant-name {
            color: #0f172a;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .detail-row {
            color: #475569;
            font-size: 9.5px;
            line-height: 1.5;
        }
        .detail-label {
            color: #64748b;
            display: inline-block;
            width: 85px;
        }
        .detail-value {
            color: #0f172a;
            font-weight: 600;
        }

        /* Line Items Table */
        .section-title {
            color: #0f172a;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .items-table {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .items-table th {
            background-color: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            color: #475569;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 0.6px;
            padding: 8px 12px;
            text-align: left;
            text-transform: uppercase;
        }
        .items-table td {
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            font-size: 9.5px;
            padding: 9px 12px;
            vertical-align: middle;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .items-table .type-tag {
            background: #f1f5f9;
            border-radius: 4px;
            color: #475569;
            display: inline-block;
            font-size: 8px;
            font-weight: 600;
            padding: 2px 6px;
            text-transform: uppercase;
        }

        /* Totals Area */
        .totals-container {
            margin-bottom: 22px;
        }
        .totals-table {
            margin-left: auto;
            width: 280px;
        }
        .totals-table td {
            font-size: 10px;
            padding: 5px 8px;
        }
        .totals-label {
            color: #64748b;
            font-weight: 600;
        }
        .totals-value {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }
        .totals-highlight td {
            border-top: 1.5px solid #0f172a;
            font-size: 12px;
            font-weight: 800;
            padding: 8px 8px 6px 8px;
        }
        .totals-outstanding-zero {
            color: #15803d;
        }
        .totals-outstanding-due {
            color: #dc2626;
        }

        /* Payment Records Table */
        .payments-table {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .payments-table th {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 6px 10px;
            text-align: left;
            text-transform: uppercase;
        }
        .payments-table td {
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 9px;
            padding: 7px 10px;
        }
        .payments-table tr:last-child td {
            border-bottom: none;
        }

        /* Footer Note */
        .footer-table {
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 8px;
            margin-top: 24px;
            padding-top: 12px;
        }
        .footer-table td {
            vertical-align: top;
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
    $statusClass = match ($rawStatus) {
        'paid' => 'status-paid',
        'partial' => 'status-partial',
        'overdue' => 'status-overdue',
        'pending' => 'status-pending',
        default => 'status-other',
    };
    $statusLabel = match ($rawStatus) {
        'paid' => 'PAID',
        'partial' => 'PARTIALLY PAID',
        'overdue' => 'OVERDUE',
        'pending' => 'PAYMENT PENDING',
        'cancelled' => 'CANCELLED',
        'void' => 'VOID',
        default => strtoupper(str_replace('_', ' ', $rawStatus)),
    };

    $tenant = $invoice->lease?->primaryTenant;
    $payments = $invoice->payments ?? collect();
    $generatedAt = now()->timezone('Asia/Jakarta')->locale($locale)->translatedFormat('d M Y, H:i');
    $isPaidInFull = (float) $invoice->outstanding <= 0;
@endphp

<!-- Header Section -->
<table class="header-table">
    <tr>
        <td style="width: 60%">
            <p class="brand-title">{{ $siteName }}</p>
            @if ($property?->name)
                <p class="brand-property">{{ $property->name }}</p>
            @endif
            @if ($propertyAddress !== '')
                <p class="brand-address">{{ $propertyAddress }}</p>
            @endif
        </td>
        <td class="text-right" style="width: 40%">
            <h1 class="invoice-title">INVOICE</h1>
            <p class="invoice-ref">{{ $invoice->reference ?? '#'.$invoice->getKey() }}</p>
            <div style="margin-top: 6px;">
                <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
        </td>
    </tr>
</table>

<!-- Info Cards Section: Bill To & Particulars -->
<table class="info-card-table">
    <tr>
        <td style="padding-right: 6px;">
            <div class="info-box">
                <p class="card-header">Billed To</p>
                <p class="tenant-name">{{ $tenant?->name ?? 'Valued Tenant' }}</p>
                <div class="detail-row">
                    @if ($invoice->lease?->unit?->name)
                        <span class="detail-label">Unit / Room:</span>
                        <span class="detail-value">{{ $invoice->lease->unit->name }}</span><br>
                    @endif
                    @if ($invoice->lease?->reference)
                        <span class="detail-label">Lease Ref:</span>
                        <span class="detail-value">{{ $invoice->lease->reference }}</span><br>
                    @endif
                    @if ($tenant?->phone)
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value">{{ $tenant->phone }}</span><br>
                    @endif
                    @if ($tenant?->user?->email)
                        <span class="detail-label">Email:</span>
                        <span class="detail-value">{{ $tenant->user->email }}</span>
                    @endif
                </div>
            </div>
        </td>
        <td style="padding-left: 6px;">
            <div class="info-box">
                <p class="card-header">Invoice Details</p>
                <div class="detail-row">
                    <span class="detail-label">Issue Date:</span>
                    <span class="detail-value">{{ $formatDate($invoice->created_at) }}</span><br>
                    <span class="detail-label">Billing Period:</span>
                    <span class="detail-value">{{ $formatDate($invoice->period_start) }} &ndash; {{ $formatDate($invoice->period_end) }}</span><br>
                    <span class="detail-label">Due Date:</span>
                    <span class="detail-value" style="{{ $rawStatus === 'overdue' ? 'color: #dc2626;' : '' }}">{{ $formatDate($invoice->due_date) }}</span><br>
                    <span class="detail-label">Invoice Ref:</span>
                    <span class="detail-value">{{ $invoice->reference ?? '#'.$invoice->getKey() }}</span>
                </div>
            </div>
        </td>
    </tr>
</table>

<!-- Line Items Section -->
<p class="section-title">Itemized Charges</p>
<table class="items-table">
    <thead>
        <tr>
            <th style="width: 55%;">Description</th>
            <th style="width: 20%;">Type</th>
            <th class="text-right" style="width: 25%;">Amount</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($invoice->lineItems as $item)
        <tr>
            <td>
                <span class="font-semibold text-dark">{{ $item->description }}</span>
            </td>
            <td>
                <span class="type-tag">{{ str($item->type)->replace('_', ' ') }}</span>
            </td>
            <td class="text-right font-semibold tabular">
                {{ $formatMoney($item->amount) }}
            </td>
        </tr>
    @empty
        <tr>
            <td class="text-center text-muted" colspan="3" style="padding: 16px;">
                No itemized charges found for this invoice.
            </td>
        </tr>
    @endforelse
    </tbody>
</table>

<!-- Totals Summary Section -->
<div class="totals-container">
    <table class="totals-table">
        <tr>
            <td class="totals-label">Subtotal / Total:</td>
            <td class="totals-value tabular">{{ $formatMoney($invoice->total) }}</td>
        </tr>
        <tr>
            <td class="totals-label">Amount Paid:</td>
            <td class="totals-value tabular" style="color: #15803d;">
                {{ $formatMoney($invoice->amount_paid) }}
            </td>
        </tr>
        <tr class="totals-highlight">
            <td class="totals-label" style="color: #0f172a;">Balance Due:</td>
            <td class="totals-value tabular {{ $isPaidInFull ? 'totals-outstanding-zero' : 'totals-outstanding-due' }}">
                {{ $formatMoney($invoice->outstanding) }}
            </td>
        </tr>
    </table>
</div>

<!-- Payments Record (if any recorded) -->
@if ($payments->isNotEmpty())
    <div style="margin-top: 10px;">
        <p class="section-title">Payment History</p>
        <table class="payments-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Date</th>
                    <th style="width: 25%;">Method</th>
                    <th style="width: 25%;">Reference</th>
                    <th class="text-right" style="width: 25%;">Amount</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($payments as $payment)
                <tr>
                    <td>{{ $formatDate($payment->payment_date) }}</td>
                    <td><span class="font-semibold">{{ str($payment->payment_method)->replace('_', ' ')->title() }}</span></td>
                    <td class="text-muted">{{ $payment->reference_number ?? 'Manual Verification' }}</td>
                    <td class="text-right font-semibold tabular" style="color: #15803d;">
                        {{ $formatMoney($payment->amount) }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<!-- Footer Section -->
<table class="footer-table">
    <tr>
        <td style="width: 60%">
            <p class="font-semibold text-dark">{{ $siteName }}</p>
            <p>Thank you for your business. For billing questions, please contact management.</p>
        </td>
        <td class="text-right" style="width: 40%">
            <p>Generated on {{ $generatedAt }} WIB</p>
            <p>This is a computer-generated document. No signature required.</p>
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
