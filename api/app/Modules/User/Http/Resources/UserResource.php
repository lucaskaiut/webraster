<?php

namespace App\Modules\User\Http\Resources;

use App\Modules\ACL\Http\Resources\RoleResource;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserWorkHour;
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
            'type' => ($this->type ?? UserType::STAFF)->value,
            'type_label' => ($this->type ?? UserType::STAFF)->label(),
            'is_master' => (bool) $this->is_master,
            'is_primary' => (bool) $this->is_primary,
            'client_id' => $this->when(
                $this->client_id !== null,
                fn () => $this->relationLoaded('client')
                    ? $this->client?->uuid
                    : $this->client()->value('uuid'),
            ),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'zip' => $this->zip,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'base_address' => $this->isTechnician() ? ($this->baseAddress() ?: null) : null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'technician_active' => (bool) $this->technician_active,
            'work_hours' => $this->whenLoaded('workHours', fn () => $this->workHours
                ->map(fn (UserWorkHour $hour) => [
                    'weekday' => $hour->weekday,
                    'starts_at' => UserWorkHour::normalizeTime((string) $hour->starts_at),
                    'ends_at' => UserWorkHour::normalizeTime((string) $hour->ends_at),
                ])
                ->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
