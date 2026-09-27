<?php

use App\Modules\Crm\Models\AiStageConfiguration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AiStageConfiguration::query()->each(function (AiStageConfiguration $config): void {
            $actions = $config->allowed_actions ?? [];

            if (! is_array($actions)) {
                $actions = [];
            }

            if (in_array('request_human_handoff', $actions, true)) {
                return;
            }

            $actions[] = 'request_human_handoff';

            $config->forceFill(['allowed_actions' => array_values($actions)])->save();
        });
    }

    public function down(): void
    {
        AiStageConfiguration::query()->each(function (AiStageConfiguration $config): void {
            $actions = $config->allowed_actions ?? [];

            if (! is_array($actions)) {
                return;
            }

            $filtered = array_values(array_filter(
                $actions,
                static fn (mixed $action): bool => $action !== 'request_human_handoff',
            ));

            $config->forceFill(['allowed_actions' => $filtered])->save();
        });
    }
};
