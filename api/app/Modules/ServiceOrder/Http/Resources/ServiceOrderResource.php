<?php

namespace App\Modules\ServiceOrder\Http\Resources;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Checklist\Http\Resources\ServiceOrderChecklistResource;
use App\Modules\Commission\Enums\CommissionStatus;
use App\Modules\Finance\Http\Resources\FinanceBillingResource;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tenant\Support\Facades\TenantContext;
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
        $user = $request->user();
        $canViewEquipmentDetails = (bool) $user?->hasPermission(Permission::EQUIPMENT_DETAILS_READ);
        $canViewCommission = $user !== null
            && $user->hasPermission(Permission::COMMISSION_READ)
            && (
                $user->hasPermission(Permission::COMMISSION_VIEW_OTHERS)
                || (int) $this->technician_id === (int) $user->getKey()
            );

        $tenant = TenantContext::tenant();
        $tenantMatches = $tenant !== null
            && (int) $tenant->getKey() === (int) $this->tenant_id;
        $requiresLoanTerm = $tenantMatches
            && (bool) $tenant->require_equipment_loan_acceptance;

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
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'scheduled_start_at' => $this->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $this->scheduled_end_at?->toIso8601String(),
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'estimated_travel_minutes' => $this->estimated_travel_minutes,
            'estimated_return_travel_minutes' => $this->estimated_return_travel_minutes,
            'travel_distance_meters' => $this->travel_distance_meters,
            'return_travel_distance_meters' => $this->return_travel_distance_meters,
            'description' => $this->description,
            'notes' => $this->notes,
            'installation_location' => $this->installation_location,
            'installation_location_photo_path' => $this->installation_location_photo_path,
            'installation_location_photo_url' => $this->installation_location_photo_path
                ? asset("storage/{$this->installation_location_photo_path}")
                : null,
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
            'attachments' => ServiceOrderAttachmentResource::collection($this->whenLoaded('attachments')),
            'billing' => $this->whenLoaded('billings', function () {
                $billing = $this->billings->first();

                return $billing ? FinanceBillingResource::make($billing) : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'histories' => ServiceOrderHistoryResource::collection($this->whenLoaded('histories')),
            // Checklists são operacionais: não são expostos ao portal do cliente.
            'checklists' => $this->when(
                $request->user()?->client_id === null,
                fn () => ServiceOrderChecklistResource::collection($this->whenLoaded('checklists')),
            ),
            'equipment_tests' => $this->when(
                $request->user()?->client_id === null,
                fn () => ServiceOrderEquipmentTestResource::collection($this->whenLoaded('equipmentTests')),
            ),
            'loan_term_required' => $requiresLoanTerm,
            'loan_term_text' => $requiresLoanTerm ? $tenant?->equipment_loan_term : null,
            'loan_term_accepted_at' => $this->loan_term_accepted_at?->toIso8601String(),
            'loan_term_accepted_text' => $this->loan_term_text,
            'loan_term_signature_available' => filled($this->loan_term_signature_path),
            'loan_term_collected_by' => $this->whenLoaded('loanTermCollectedBy', fn () => $this->loanTermCollectedBy ? [
                'id' => $this->loanTermCollectedBy->uuid,
                'name' => $this->loanTermCollectedBy->name,
            ] : null),
            // Apenas o valor absoluto; nunca o percentual ou o total da OS.
            'commission' => $this->when(
                $canViewCommission,
                fn () => $this->relationLoaded('commission')
                    && $this->commission
                    && $this->commission->status !== CommissionStatus::CANCELLED
                    ? [
                        'amount_cents' => $this->commission->amount_cents,
                        'amount' => number_format($this->commission->amount_cents / 100, 2, '.', ''),
                    ]
                    : null,
            ),
        ];
    }
}
