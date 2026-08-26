<?php

namespace App\Console\Commands;

use App\Services\N8nWebhookService;
use Illuminate\Console\Command;

class PushVacantRoomsToN8n extends Command
{
    protected $signature = 'openkos:push-n8n-vacant {--url= : Optional target n8n webhook URL}';

    protected $description = 'Push all vacant/empty rooms in OpenKos to n8n webhook';

    public function handle(N8nWebhookService $service): int
    {
        $targetUrl = $this->option('url');
        $this->info('Pushing vacant rooms to n8n webhook...');

        $result = $service->pushAllVacantRooms($targetUrl);

        if ($result['status'] === 'success') {
            $this->info("Successfully pushed vacant rooms to n8n (HTTP {$result['http_code']}).");

            return self::SUCCESS;
        }

        if ($result['status'] === 'skipped') {
            $this->warn($result['message']);

            return self::SUCCESS;
        }

        $this->error('Failed to push to n8n: '.($result['message'] ?? $result['error'] ?? 'Unknown error'));

        return self::FAILURE;
    }
}
