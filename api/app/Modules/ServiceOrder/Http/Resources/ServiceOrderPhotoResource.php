<?php

namespace App\Modules\ServiceOrder\Http\Resources;

use App\Modules\ServiceOrder\Models\ServiceOrderPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceOrderPhoto
 */
class ServiceOrderPhotoResource extends JsonResource
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
