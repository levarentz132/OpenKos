<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DeploymentSmokeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $cacheKey) {}

    public function handle(): void
    {
        Cache::put($this->cacheKey, true, 60);
    }

    public function failed(Throwable $exception): void
    {
        Cache::put($this->cacheKey, false, 60);
    }
}
