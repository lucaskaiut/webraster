<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tracking\Services\TraccarWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TraccarWebhookController extends ApiController
{
    public function __construct(private readonly TraccarWebhookService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->assertAuthorized($request);

        $this->service->ingest($request->all());

        return $this->success(['ok' => true]);
    }

    private function assertAuthorized(Request $request): void
    {
        $secret = (string) config('traccar.webhook_secret', '');

        if ($secret === '') {
            return;
        }

        $provided = $request->header('X-Traccar-Webhook-Token', '');

        if (! is_string($provided) || ! hash_equals($secret, $provided)) {
            abort(403);
        }
    }
}
