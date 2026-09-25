<?php

namespace App\Modules\Notification\Http\Controllers;

use App\Modules\Client\Models\Client;
use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\Notification\Enums\NotificationSource;
use App\Modules\Notification\Http\Requests\SendNotificationRequest;
use App\Modules\Notification\Services\NotificationEngine;
use App\Modules\Notification\Services\NotificationRecipientResolver;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class NotificationSendController extends ApiController
{
    public function __construct(
        private readonly NotificationEngine $engine,
        private readonly NotificationRecipientResolver $resolver,
    ) {}

    /**
     * Notificação manual enviada pelo painel (in-app + push).
     */
    public function store(SendNotificationRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = (int) $user->tenant_id;

        $recipients = match ($request->validated('audience')) {
            'tenant' => $this->resolver->forTenant($tenantId),
            'client' => $this->resolver->forClient($this->resolveClientId($request)),
            'users' => $this->resolver->forUsers($tenantId, $request->validated('user_ids')),
        };

        $created = $this->engine->send($recipients, new NotificationMessage(
            type: 'manual',
            title: $request->validated('title'),
            body: $request->validated('body'),
            data: $request->validated('data') ?? [],
            source: NotificationSource::MANUAL,
            createdBy: (int) $user->getKey(),
            push: $request->boolean('push', true),
        ));

        return $this->created(
            ['recipients' => $created],
            'Notificação enviada.',
        );
    }

    private function resolveClientId(SendNotificationRequest $request): int
    {
        return (int) Client::query()
            ->where('uuid', $request->validated('client_id'))
            ->value('id');
    }
}
