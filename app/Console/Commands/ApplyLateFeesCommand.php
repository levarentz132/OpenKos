<?php

namespace App\Console\Commands;

use App\Actions\Invoices\ApplyLateFees;
use Illuminate\Console\Command;

class ApplyLateFeesCommand extends Command
{
    protected $signature = 'invoices:apply-late-fees';

    protected $description = 'Apply late fee charges to overdue invoices';

    public function handle(ApplyLateFees $action): int
    {
        $count = $action->execute();

        $this->info("Applied late fees to {$count} invoice(s).");

        return Command::SUCCESS;
    }
}
