<?php

namespace App\Modules\ServiceOrder\Http\Resources;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Finance\Http\Resources\FinanceBillingResource;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceOrder
 */
class ServiceOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewEquipmentDetails = (bool) $request->user()?->hasPermission(Permission::EQUIPMENT_DETAILS_READ);

        return [
            'id' => $this->uuid,
            'number' => $this->number,
            'code' => $this->code,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'client_id' => $this->client?->uuid,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->uuid,
                'name' => $this->client->name,
            ] : null),
            'vehicle_id' => $this->vehicle?->uuid,
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id' => $this->vehicle->uuid,
                'plate' => $this->vehicle->plate,
                'brand' => $this->vehicle->brand,
                'model' => $this->vehicle->model,
            ] : null),
            'equipment_id' => $this->equipment?->uuid,
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id' => $this->equipment->uuid,
                'imei' => $canViewEquipmentDetails ? $this->equipment->imei : null,
                'model' => $canViewEquipmentDetails ? $this->equipment->model : null,
            ] : null),
            'technician_id' => $this->technician?->uuid,
            'technician' => $this->whenLoaded('technician', fn () => $this->technician ? [
                'id' => $this->technician->uuid,
                'name' => $this->technician->name,
            ] : null),
            'scheduled_start_at' => $this->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $this->scheduled_end_at?->toIso8601String(),
            'description' => $this->description,
            'notes' => $this->notes,
            'execution_notes' => $this->execution_notes,
            'cancellation_reason' => $this->cancellation_reason,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->uuid,
                'name' => $this->creator->name,
            ] : null),
            'completed_by' => $this->whenLoaded('completer', fn () => $this->completer ? [
                'id' => $this->completer->uuid,
                'name' => $this->completer->name,
            ] : null),
            'cancelled_by' => $this->whenLoaded('canceller', fn () => $this->canceller ? [
                'id' => $this->canceller->uuid,
                'name' => $this->canceller->name,
            ] : null),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'items' => ServiceOrderItemResource::collection($this->whenLoaded('items')),
            'items_total_cents' => $this->whenLoaded('items', fn () => $this->itemsTotalCents()),
            'items_total' => $this->whenLoaded(
                'items',
                fn () => number_format($this->itemsTotalCents() / 100, 2, '.', ''),
            ),
            'photos' => ServiceOrderPhotoResource::collection($this->whenLoaded('photos')),
            'billing' => $this->whenLoaded('billings', function () {
                $billing = $this->billings->first();

                return $billing ? FinanceBillingResource::make($billing) : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'histories' => ServiceOrderHistoryResource::collection($this->whenLoaded('histories')),
        ];
    }
}
