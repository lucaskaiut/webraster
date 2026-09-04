<?php

namespace App\Modules\Webhook\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Webhook\Http\Requests\StoreWebhookRequest;
use App\Modules\Webhook\Http\Requests\UpdateWebhookRequest;
use App\Modules\Webhook\Http\Resources\WebhookLogResource;
use App\Modules\Webhook\Http\Resources\WebhookResource;
use App\Modules\Webhook\Models\Webhook;
use App\Modules\Webhook\Services\WebhookEventRegistry;
use App\Modules\Webhook\Services\WebhookService;
use Illuminate\Http\JsonResponse;

class WebhookController extends ApiController
{
    public function __construct(
        private readonly WebhookService $service,
        private readonly WebhookEventRegistry $eventRegistry,
    ) {}

    public function events(): JsonResponse
    {
        return $this->success($this->eventRegistry->all());
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Webhook::class);

        return $this->success(WebhookResource::collection($this->service->list()));
    }

    public function store(StoreWebhookRequest $request): JsonResponse
    {
        $this->authorize('create', Webhook::class);

        return $this->created(
            WebhookResource::make($this->service->create($request->validated())),
            'Webhook criado com sucesso.',
        );
    }

    public function show(Webhook $webhook): JsonResponse
    {
        $this->authorize('view', $webhook);

        return $this->success(WebhookResource::make($webhook));
    }

    public function update(UpdateWebhookRequest $request, Webhook $webhook): JsonResponse
    {
        $this->authorize('update', $webhook);

        return $this->success(
            WebhookResource::make($this->service->update($webhook, $request->validated())),
            'Webhook atualizado com sucesso.',
        );
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        $this->authorize('delete', $webhook);
        $this->service->delete($webhook);

        return $this->success(null, 'Webhook removido com sucesso.');
    }

    public function logs(Webhook $webhook): JsonResponse
    {
        $this->authorize('view', $webhook);

        return $this->success(WebhookLogResource::collection($this->service->getLogs($webhook)));
    }
}
