<?php

namespace App\Modules\Alert\Http\Resources;

use App\Modules\Alert\Models\AlertConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configuração de alerta no formato do cliente (checkbox simples).
 *
 * @mixin AlertConfig
 */
class ClientAlertConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->type->label(),
            'description' => $this->type->description(),
            'is_enabled' => (bool) $this->is_enabled,
        ];
    }
}
