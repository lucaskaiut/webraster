<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Enums\LeadStatus;
use App\Modules\Crm\Events\LeadStageChanged;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LeadService
{
    public function moveToStage(Lead $lead, PipelineStage $stage, ?Tenant $tenant = null): Lead
    {
        if ((int) $stage->pipeline_id !== (int) $lead->pipeline_id) {
            throw ValidationException::withMessages([
                'stage_id' => ['A etapa deve pertencer ao mesmo funil do lead.'],
            ]);
        }

        return DB::transaction(function () use ($lead, $stage, $tenant): Lead {
            $previousStageId = $lead->stage_id;

            $lead->forceFill([
                'stage_id' => $stage->getKey(),
                'status' => $stage->is_won
                    ? LeadStatus::WON
                    : ($stage->is_lost ? LeadStatus::LOST : LeadStatus::OPEN),
                'last_interaction_at' => now(),
            ])->save();

            if ($previousStageId !== $lead->stage_id) {
                $tenant ??= TenantContext::tenant() ?? $lead->tenant;
                if ($tenant !== null) {
                    LeadStageChanged::dispatch($tenant, $lead->fresh(['contact', 'stage', 'pipeline', 'owner']));
                }
            }

            return $lead;
        });
    }

    public function paginate(int $perPage = 20, ?int $pipelineId = null, ?int $stageId = null, ?string $search = null)
    {
        return Lead::query()
            ->with(['contact', 'stage', 'pipeline', 'owner'])
            ->when($pipelineId, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->when($stageId, fn ($q) => $q->where('stage_id', $stageId))
            ->when($search, function ($q) use ($search): void {
                $q->whereHas('contact', function ($contact) use ($search): void {
                    $contact->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('last_interaction_at')
            ->paginate($perPage);
    }

    /**
     * @return array<string, list<Lead>>
     */
    public function kanban(int $pipelineId): array
    {
        $stages = PipelineStage::query()
            ->where('pipeline_id', $pipelineId)
            ->where('active', true)
            ->orderBy('position')
            ->get();

        $grouped = [];

        foreach ($stages as $stage) {
            $grouped[$stage->uuid] = Lead::query()
                ->with(['contact', 'owner'])
                ->where('pipeline_id', $pipelineId)
                ->where('stage_id', $stage->getKey())
                ->where('status', LeadStatus::OPEN)
                ->orderByDesc('last_interaction_at')
                ->limit(100)
                ->get()
                ->all();
        }

        return $grouped;
    }
}
