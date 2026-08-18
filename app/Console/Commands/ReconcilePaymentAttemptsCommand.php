<?php

namespace App\Console\Commands;

use App\Actions\Payments\ReconcilePaymentAttempt;
use App\Models\PaymentAttempt;
use App\Results\Payment\ReconcilePaymentAttemptResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:reconcile {--limit=100 : Maximum attempts to inspect}')]
#[Description('Reconcile pending payment gateway attempts')]
final class ReconcilePaymentAttemptsCommand extends Command
{
    private const MAX_RUNTIME_SECONDS = 240;

    public function handle(ReconcilePaymentAttempt $reconcile): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $now = now();
        $deadline = microtime(true) + self::MAX_RUNTIME_SECONDS;
        $attempts = PaymentAttempt::query()
            ->whereNotNull('provider_reference')
            ->reconciliationCandidate($now->copy()->subMinutes(5), $now)
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $counts = [];
        $processed = 0;

        foreach ($attempts as $attempt) {
            if (microtime(true) >= $deadline) {
                break;
            }

            try {
                $result = $reconcile->execute($attempt);
                $counts[$result->status] = ($counts[$result->status] ?? 0) + 1;
            } catch (\Throwable $exception) {
                $counts[ReconcilePaymentAttemptResult::FAILED] = ($counts[ReconcilePaymentAttemptResult::FAILED] ?? 0) + 1;
                report($exception);
            }

            $processed++;
        }

        $this->info(sprintf(
            'Reconciled %d payment attempt(s): %s.',
            $processed,
            collect($counts)->map(fn (int $count, string $status): string => "{$status}={$count}")->implode(', ') ?: 'none',
        ));

        return self::SUCCESS;
    }
}
