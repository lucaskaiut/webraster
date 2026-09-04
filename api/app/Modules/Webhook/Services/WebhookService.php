<?php

namespace App\Modules\Webhook\Services;

use App\Modules\Webhook\Models\Webhook;
use App\Modules\Webhook\Models\WebhookLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class WebhookService
{
    public function list(): Collection
    {
        return Webhook::query()->latest()->get();
    }

    public function create(array $data): Webhook
    {
        return Webhook::query()->create($data);
    }

    public function update(Webhook $webhook, array $data): Webhook
    {
        $webhook->fill($data)->save();

        return $webhook->fresh();
    }

    public function delete(Webhook $webhook): void
    {
        $webhook->delete();
    }

    public function getActiveByEvent(string $event): Collection
    {
        return Webhook::query()
            ->where('event', $event)
            ->where('is_active', true)
            ->get();
    }

    public function getLogs(Webhook $webhook): Collection
    {
        return $webhook->logs()->latest()->limit(50)->get();
    }
}
