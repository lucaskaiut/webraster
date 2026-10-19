<?php

namespace App\Modules\ServiceOrder\Http\Resources;

use App\Modules\ServiceOrder\Models\ServiceOrderAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceOrderAttachment
 */
class ServiceOrderAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader ? [
                'id' => $this->uploader->uuid,
                'name' => $this->uploader->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
