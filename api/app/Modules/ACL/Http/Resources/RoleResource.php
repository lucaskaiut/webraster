<?php

namespace App\Modules\ACL\Http\Resources;

use App\Modules\ACL\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'description' => $this->description,
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissionValues(),
            ),
        ];
    }
}
