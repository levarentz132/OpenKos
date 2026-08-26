<?php

namespace App\Http\Controllers;

use App\Services\N8nWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class N8nWebSyncController extends Controller
{
    public function preview(N8nWebhookService $service): JsonResponse
    {
        $webhookUrl = config('services.n8n.webhook_url', env('N8N_WEBHOOK_URL'));
        $payload = $service->getBulkPayload();

        return response()->json([
            'status' => 'success',
            'configured' => ! empty($webhookUrl),
            'webhook_url' => $webhookUrl ?: 'Not configured in .env (N8N_WEBHOOK_URL)',
            'payload' => $payload,
        ]);
    }

    public function sync(Request $request, N8nWebhookService $service): RedirectResponse|JsonResponse
    {
        $result = $service->pushAllVacantRooms();

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        if ($result['status'] === 'success') {
            $count = $result['response']['count'] ?? 'all';

            return back()->with('success', "Pushed {$count} vacant rooms to n8n successfully!");
        }

        if ($result['status'] === 'skipped') {
            return back()->with('warning', $result['message'] ?? 'N8N_WEBHOOK_URL is not configured in .env.');
        }

        return back()->with('error', 'Failed to push to n8n: '.($result['message'] ?? $result['error'] ?? 'Unknown error'));
    }
}
