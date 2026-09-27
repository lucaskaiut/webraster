<?php

use App\Modules\Crm\Models\AiConfiguration;
use App\Modules\Crm\Models\AiStageConfiguration;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Crm\Support\DefaultCrmAiPrompts;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $stagePrompts = DefaultCrmAiPrompts::stagePromptsByName();
        $systemPrompt = DefaultCrmAiPrompts::systemPrompt();

        AiConfiguration::query()->each(function (AiConfiguration $config) use ($systemPrompt): void {
            $config->forceFill(['system_prompt' => $systemPrompt])->save();
        });

        PipelineStage::query()
            ->where('active', true)
            ->orderBy('pipeline_id')
            ->orderBy('position')
            ->each(function (PipelineStage $stage) use ($stagePrompts): void {
                $definition = $stagePrompts[$stage->name] ?? null;

                if ($definition === null) {
                    return;
                }

                AiStageConfiguration::query()->updateOrCreate(
                    ['stage_id' => $stage->getKey()],
                    [
                        'tenant_id' => $stage->tenant_id,
                        'enabled' => $definition['enabled'],
                        'objective' => $definition['objective'],
                        'instructions' => $definition['instructions'],
                        'success_criteria' => $definition['success_criteria'],
                        'allowed_actions' => $definition['allowed_actions'],
                    ],
                );
            });
    }

    public function down(): void
    {
        // Conteúdo editorial — não reverte automaticamente.
    }
};
