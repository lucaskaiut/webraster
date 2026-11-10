<?php

namespace App\Modules\Finance\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Support\FinanceNotificationMessage;
use App\Modules\Notification\DTOs\NotificationMessage;
use App\Modules\Notification\Enums\NotificationSource;
use App\Modules\Notification\Services\NotificationEngine;
use App\Modules\Notification\Services\NotificationRecipientResolver;

/**
 * Entrega um aviso financeiro ao cliente: in-app + push para os usuários do
 * portal e e-mail para o endereço financeiro (ou o principal) do cadastro.
 */
class FinanceClientNotifier
{
    public function __construct(
        private readonly FinanceNotificationService $emails,
        private readonly NotificationEngine $engine,
        private readonly NotificationRecipientResolver $recipients,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(?Client $client, string $type, string $subject, string $body, array $data = []): void
    {
        if ($client === null) {
            return;
        }

        $this->engine->send($this->recipients->forClient($client), new NotificationMessage(
            type: $type,
            title: $subject,
            body: $body,
            data: $data,
            source: NotificationSource::FINANCE,
            push: true,
        ));

        $to = $client->financial_email ?: $client->email;

        if (blank($to)) {
            return;
        }

        $this->emails->notify(new FinanceNotificationMessage(
            to: (string) $to,
            subject: $subject,
            body: $body,
            data: $data,
            clientId: $client->getKey(),
        ));
    }
}
