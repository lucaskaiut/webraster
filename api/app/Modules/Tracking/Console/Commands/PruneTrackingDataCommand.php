<?php

namespace App\Modules\Tracking\Console\Commands;

use App\Modules\Tracking\Enums\TraccarEventStatus;
use App\Modules\Tracking\Models\EquipmentRawLog;
use App\Modules\Tracking\Models\TraccarEvent;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpeza de dados antigos que NÃO alimentam os relatórios dos clientes.
 *
 * Garantia: as tabelas dos relatórios de posição (gps_positions, alerts e
 * device_command_logs) exigem retenção mínima de 6 meses e NUNCA são tocadas
 * aqui — ver config/pruning.php.
 *
 * Etapas:
 *  - traccar_events: apenas status terminais (processed/ignored/failed);
 *    pending/processing são preservados independentemente da idade.
 *  - equipment_raw_logs: log bruto interno de diagnóstico.
 *  - tc_positions (conexão "traccar"): histórico bruto do gateway; a etapa é
 *    pulada quando TRACCAR_DB_PASSWORD não está configurada.
 */
class PruneTrackingDataCommand extends Command
{
    protected $signature = 'tracking:prune
        {--dry-run : Apenas conta os registros que seriam removidos}
        {--only= : Executa somente uma etapa (events|raw-logs|positions)}';

    protected $description = 'Remove dados antigos sem impacto nos relatórios (retenção configurável)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batchSize = max(500, (int) config('pruning.batch_size', 5000));

        $this->pruneTraccarEvents($dryRun, $batchSize);
        $this->pruneEquipmentRawLogs($dryRun, $batchSize);
        $this->pruneTraccarPositions($dryRun, $batchSize);

        return self::SUCCESS;
    }

    private function pruneTraccarEvents(bool $dryRun, int $batchSize): void
    {
        if (! $this->shouldRun('events')) {
            return;
        }

        $days = (int) config('pruning.traccar_events_days', 30);
        $cutoff = now()->subDays($days);
        $statuses = [
            TraccarEventStatus::PROCESSED->value,
            TraccarEventStatus::IGNORED->value,
            TraccarEventStatus::FAILED->value,
        ];

        $query = fn () => TraccarEvent::query()
            ->whereIn('status', $statuses)
            ->where('received_at', '<', $cutoff);

        $this->runStep(
            label: "traccar_events (status terminais com mais de {$days} dias)",
            dryRun: $dryRun,
            batchSize: $batchSize,
            count: fn (): int => $query()->count(),
            deleteBatch: fn (): int => $query()->limit($batchSize)->delete(),
        );
    }

    private function pruneEquipmentRawLogs(bool $dryRun, int $batchSize): void
    {
        if (! $this->shouldRun('raw-logs')) {
            return;
        }

        $days = (int) config('pruning.equipment_raw_logs_days', 30);
        $cutoff = now()->subDays($days);

        $query = fn () => EquipmentRawLog::query()
            ->withoutTenancy()
            ->where('received_at', '<', $cutoff);

        $this->runStep(
            label: "equipment_raw_logs (com mais de {$days} dias)",
            dryRun: $dryRun,
            batchSize: $batchSize,
            count: fn (): int => $query()->count(),
            deleteBatch: fn (): int => $query()->limit($batchSize)->delete(),
        );
    }

    private function pruneTraccarPositions(bool $dryRun, int $batchSize): void
    {
        if (! $this->shouldRun('positions')) {
            return;
        }

        if (blank(config('database.connections.traccar.password'))) {
            $this->warn('tc_positions: conexão "traccar" não configurada (TRACCAR_DB_PASSWORD) — etapa pulada.');

            return;
        }

        $days = (int) config('pruning.traccar_positions_days', 60);
        // O Traccar grava os horários em UTC — o corte precisa usar o mesmo fuso.
        $cutoff = now()->utc()->subDays($days);

        $query = fn () => DB::connection('traccar')->table('tc_positions')
            ->where('servertime', '<', $cutoff);

        $this->runStep(
            label: "tc_positions (banco do gateway, com mais de {$days} dias)",
            dryRun: $dryRun,
            batchSize: $batchSize,
            count: fn (): int => $query()->count(),
            deleteBatch: fn (): int => $query()->limit($batchSize)->delete(),
        );
    }

    private function shouldRun(string $step): bool
    {
        $only = (string) ($this->option('only') ?? '');

        return $only === '' || $only === $step;
    }

    /**
     * Executa uma etapa em lotes (evita locks longos em tabelas grandes).
     *
     * @param  Closure(): int  $count
     * @param  Closure(): int  $deleteBatch
     */
    private function runStep(string $label, bool $dryRun, int $batchSize, Closure $count, Closure $deleteBatch): void
    {
        if ($dryRun) {
            $this->line(sprintf('%s: %d registro(s) seriam removidos (dry-run).', $label, $count()));

            return;
        }

        $total = 0;

        do {
            $deleted = $deleteBatch();
            $total += $deleted;
        } while ($deleted >= $batchSize);

        $this->info(sprintf('%s: %d registro(s) removidos.', $label, $total));
    }
}
