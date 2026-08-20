<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApplyLateFees
{
    public function execute(): int
    {
        $enabled = (bool) (Setting::get('late_fee_enabled') ?? false);
        if (! $enabled) {
            return 0;
        }

        $type = (string) (Setting::get('late_fee_type') ?? 'flat');
        $amount = (float) (Setting::get('late_fee_amount') ?? 50000);
        $graceDays = max(0, (int) (Setting::get('late_fee_grace_days') ?? 3));

        $today = Carbon::today();
        $cutoffDate = $today->copy()->subDays($graceDays);

        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Pending, InvoiceStatus::Partial])
            ->where('due_date', '<=', $cutoffDate->toDateString())
            ->with('lineItems')
            ->get();

        $appliedCount = 0;

        foreach ($invoices as $invoice) {
            $daysOverdue = (int) Carbon::parse($invoice->due_date)->diffInDays($today);

            DB::transaction(function () use ($invoice, $type, $amount, $graceDays, $daysOverdue, &$appliedCount) {
                $existingLateFeeItems = $invoice->lineItems->where('type', 'late_fee');

                if ($type === 'flat') {
                    if ($existingLateFeeItems->isNotEmpty()) {
                        return;
                    }

                    $feeAmount = $amount;
                    $description = __('Late Fee (:days days overdue)', ['days' => $daysOverdue]);

                    InvoiceLineItem::create([
                        'invoice_id' => $invoice->id,
                        'type' => 'late_fee',
                        'description' => $description,
                        'amount' => $feeAmount,
                    ]);

                    $appliedCount++;
                } elseif ($type === 'percentage') {
                    if ($existingLateFeeItems->isNotEmpty()) {
                        return;
                    }

                    $rentSum = $invoice->lineItems->where('type', 'rent')->sum('amount');
                    if ($rentSum <= 0) {
                        $rentSum = (float) $invoice->total;
                    }

                    $feeAmount = round(($rentSum * $amount) / 100, 2);
                    $description = __('Late Fee (:rate% - :days days overdue)', [
                        'rate' => $amount,
                        'days' => $daysOverdue,
                    ]);

                    InvoiceLineItem::create([
                        'invoice_id' => $invoice->id,
                        'type' => 'late_fee',
                        'description' => $description,
                        'amount' => $feeAmount,
                    ]);

                    $appliedCount++;
                } elseif ($type === 'daily_flat') {
                    $daysPastGrace = max(1, $daysOverdue - $graceDays);
                    $existingCount = $existingLateFeeItems->count();

                    if ($existingCount >= $daysPastGrace) {
                        return;
                    }

                    $missingDays = $daysPastGrace - $existingCount;

                    for ($i = 0; $i < $missingDays; $i++) {
                        $dayNumber = $existingCount + $i + 1;
                        $description = __('Daily Late Fee (Day :day overdue)', ['day' => $dayNumber + $graceDays]);

                        InvoiceLineItem::create([
                            'invoice_id' => $invoice->id,
                            'type' => 'late_fee',
                            'description' => $description,
                            'amount' => $amount,
                        ]);

                        $appliedCount++;
                    }
                }

                // Update total
                $newTotal = $invoice->lineItems()->sum('amount');
                $invoice->update(['total' => $newTotal]);
            });
        }

        return $appliedCount;
    }
}
