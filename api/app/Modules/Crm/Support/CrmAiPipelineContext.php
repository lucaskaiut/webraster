<?php

namespace App\Modules\Crm\Support;

use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\PipelineStage;

final class CrmAiPipelineContext
{
    /**
     * @return list<array{stage_id: string, name: string, position: int, is_initial: bool, is_won: bool, is_lost: bool}>
     */
    public function stagesForLead(Lead $lead): array
    {
        if ($lead->pipeline_id === null) {
            return [];
        }

        return PipelineStage::query()
            ->where('pipeline_id', $lead->pipeline_id)
            ->where('active', true)
            ->orderBy('position')
            ->get()
            ->map(static fn (PipelineStage $stage): array => [
                'stage_id' => $stage->uuid,
                'name' => $stage->name,
                'position' => $stage->position,
                'is_initial' => $stage->is_initial,
                'is_won' => $stage->is_won,
                'is_lost' => $stage->is_lost,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{stage_id: string, name: string, position: int, is_initial?: bool, is_won?: bool, is_lost?: bool}>  $stages
     */
    public function formatStagesForPrompt(array $stages): string
    {
        if ($stages === []) {
            return '(Nenhuma etapa ativa no funil.)';
        }

        $lines = [];

        foreach ($stages as $stage) {
            $flags = array_filter([
                ! empty($stage['is_initial']) ? 'inicial' : null,
                ! empty($stage['is_won']) ? 'ganho' : null,
                ! empty($stage['is_lost']) ? 'perdido' : null,
            ]);
            $suffix = $flags !== [] ? ' ['.implode(', ', $flags).']' : '';
            $lines[] = sprintf(
                '- %s — %s (posição %d)%s',
                $stage['stage_id'],
                $stage['name'],
                $stage['position'],
                $suffix,
            );
        }

        return implode("\n", $lines);
    }
}
