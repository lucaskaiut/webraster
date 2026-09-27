<?php

namespace Tests\Unit\Crm;

use App\Modules\Crm\Support\CrmAiPipelineContext;
use PHPUnit\Framework\TestCase;

class CrmAiPipelineContextTest extends TestCase
{
    public function test_formats_stage_catalog_for_prompt(): void
    {
        $context = new CrmAiPipelineContext;

        $formatted = $context->formatStagesForPrompt([
            [
                'stage_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'name' => 'Demonstração',
                'position' => 2,
                'is_initial' => false,
                'is_won' => false,
                'is_lost' => false,
            ],
        ]);

        $this->assertStringContainsString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $formatted);
        $this->assertStringContainsString('Demonstração', $formatted);
        $this->assertStringContainsString('posição 2', $formatted);
    }
}
