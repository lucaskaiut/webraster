<?php

namespace App\Modules\ServiceOrder\Http\Resources;

use Illuminate\Http\Request;

/**
 * OS vista pelo técnico responsável: inclui contato do cliente e dados do
 * equipamento, necessários para executar a instalação.
 */
class TechnicianServiceOrderResource extends ServiceOrderResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        // O técnico não recebe o valor total da OS.
        unset($data['items_total_cents'], $data['items_total']);

        return array_merge($data, [
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->uuid,
                'name' => $this->client->name,
                'phone' => $this->client->phone,
                'email' => $this->client->email,
            ] : null),
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id' => $this->equipment->uuid,
                'imei' => $this->equipment->imei,
                'model' => $this->equipment->model,
            ] : null),
        ]);
    }
}
