<?php

namespace App\Modules\Vehicle\Http\Resources;

use App\Modules\Vehicle\Models\VehicleImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleImage
 */
class VehicleImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'url' => asset("storage/{$this->path}"),
            'path' => $this->path,
            'sort_order' => $this->sort_order,
        ];
    }
}
