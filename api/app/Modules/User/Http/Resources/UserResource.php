<?php

namespace App\Modules\User\Http\Resources;

use App\Modules\ACL\Http\Resources\RoleResource;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'document' => $this->document,
            'is_master' => (bool) $this->is_master,
            'client_id' => $this->when(
                $this->client_id !== null,
                fn () => $this->relationLoaded('client')
                    ? $this->client?->uuid
                    : $this->client()->value('uuid'),
            ),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
