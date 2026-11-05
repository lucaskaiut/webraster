<?php

namespace App\Modules\ExternalApi\Http\Resources;

use App\Modules\ExternalApi\Models\ExternalApiRequest;
use App\Modules\ExternalApi\Support\ExternalApiCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExternalApiRequest
 */
class ExternalApiRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detailed = $this->additional['detailed'] ?? false;

        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'service' => $this->service,
            'service_label' => app(ExternalApiCatalog::class)->label($this->service),
            'method' => $this->method,
            'url' => $this->url,
            'status_code' => $this->status_code,
            'succeeded' => $this->succeeded,
            'duration_ms' => $this->duration_ms,
            'error' => $this->error,
            'origin' => $this->origin,
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant ? ['id' => $this->tenant->id, 'name' => $this->tenant->name] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            $this->mergeWhen($detailed, fn () => [
                'request_headers' => $this->request_headers ?? [],
                'request_body' => $this->request_body,
                'response_headers' => $this->response_headers ?? [],
                'response_body' => $this->response_body,
            ]),
        ];
    }
}
