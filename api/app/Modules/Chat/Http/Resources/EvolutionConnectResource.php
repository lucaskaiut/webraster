<?php

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\DTOs\EvolutionConnectResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EvolutionConnectResult */
class EvolutionConnectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EvolutionConnectResult $result */
        $result = $this->resource;

        return [
            'state' => $result->state,
            'qrcode_base64' => $result->qrcodeBase64,
            'pairing_code' => $result->pairingCode,
            'count' => $result->count,
        ];
    }
}
