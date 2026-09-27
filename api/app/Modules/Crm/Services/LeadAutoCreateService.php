<?php

namespace App\Modules\Crm\Services;

use App\Modules\Chat\Models\Contact;
use App\Modules\Chat\Models\MessagingConnection;
use App\Modules\Crm\Enums\LeadStatus;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pipeline;
use App\Modules\Crm\Models\PipelineStage;
use Illuminate\Support\Facades\DB;

final class LeadAutoCreateService
{
    public function createFromInbound(Contact $contact, MessagingConnection $connection): Lead
    {
        return DB::transaction(function () use ($contact, $connection): Lead {
            $existing = Lead::query()
                ->where('tenant_id', $contact->tenant_id)
                ->where('contact_id', $contact->getKey())
                ->where('status', LeadStatus::OPEN)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $pipeline = Pipeline::query()
                ->where('tenant_id', $contact->tenant_id)
                ->where('is_default', true)
                ->where('active', true)
                ->first()
                ?? Pipeline::query()
                    ->where('tenant_id', $contact->tenant_id)
                    ->where('active', true)
                    ->orderBy('id')
                    ->first();

            if ($pipeline === null) {
                $pipeline = Pipeline::query()->create([
                    'tenant_id' => $contact->tenant_id,
                    'name' => 'Vendas',
                    'description' => 'Funil padrão',
                    'active' => true,
                    'is_default' => true,
                ]);

                PipelineStage::query()->create([
                    'tenant_id' => $contact->tenant_id,
                    'pipeline_id' => $pipeline->getKey(),
                    'name' => 'Novo lead',
                    'position' => 0,
                    'color' => '#6366f1',
                    'is_initial' => true,
                    'active' => true,
                ]);
            }

            $stage = PipelineStage::query()
                ->where('pipeline_id', $pipeline->getKey())
                ->where('is_initial', true)
                ->where('active', true)
                ->orderBy('position')
                ->first()
                ?? PipelineStage::query()
                    ->where('pipeline_id', $pipeline->getKey())
                    ->orderBy('position')
                    ->firstOrFail();

            return Lead::query()->create([
                'tenant_id' => $contact->tenant_id,
                'contact_id' => $contact->getKey(),
                'pipeline_id' => $pipeline->getKey(),
                'stage_id' => $stage->getKey(),
                'status' => LeadStatus::OPEN,
                'source' => $connection->provider,
                'ai_enabled' => true,
                'last_interaction_at' => now(),
            ]);
        });
    }
}
