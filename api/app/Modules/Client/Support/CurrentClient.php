<?php

namespace App\Modules\Client\Support;

use App\Modules\Client\Models\Client;

/**
 * Contexto de isolamento por cliente (portal do cliente).
 *
 * Quando resolvido, modelos com BelongsToClient são filtrados
 * automaticamente por client_id — análogo ao CurrentTenant.
 */
final class CurrentClient
{
    private ?Client $client = null;

    public function set(Client $client): void
    {
        $this->client = $client;
    }

    public function forget(): void
    {
        $this->client = null;
    }

    public function client(): ?Client
    {
        return $this->client;
    }

    public function clientId(): ?int
    {
        return $this->client?->getKey();
    }

    public function isResolved(): bool
    {
        return $this->client !== null;
    }
}
