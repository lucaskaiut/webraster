<?php

namespace App\Modules\Alert\Services;

use App\Modules\Alert\Enums\AlertStatus;
use App\Modules\Alert\Models\Alert;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AlertService
{
    public function paginate(
        int $perPage = 15,
        ?int $vehicleId = null,
        ?string $type = null,
        ?string $status = null,
        ?string $severity = null,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        string $sort = 'desc',
    ): LengthAwarePaginator {
        return Alert::query()
            ->with(['vehicle', 'equipment', 'client'])
            ->when($vehicleId !== null, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($severity !== null, fn ($q) => $q->where('severity', $severity))
            ->when($from !== null, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->orderBy('occurred_at', $sort === 'asc' ? 'asc' : 'desc')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Alert>
     */
    public function listForMap(?string $status = 'open', int $limit = 200)
    {
        return Alert::query()
            ->with(['vehicle'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    public function acknowledge(Alert $alert, User $user): Alert
    {
        if ($alert->status === AlertStatus::RESOLVED) {
            return $alert;
        }

        $alert->forceFill([
            'status' => AlertStatus::ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->getKey(),
        ])->save();

        return $alert->refresh()->load(['vehicle', 'equipment', 'client']);
    }

    public function resolve(Alert $alert, User $user): Alert
    {
        $alert->forceFill([
            'status' => AlertStatus::RESOLVED,
            'resolved_at' => now(),
            'acknowledged_at' => $alert->acknowledged_at ?? now(),
            'acknowledged_by' => $alert->acknowledged_by ?? $user->getKey(),
        ])->save();

        return $alert->refresh()->load(['vehicle', 'equipment', 'client']);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardStats(): array
    {
        $now = CarbonImmutable::now();

        $today = Alert::query()->where('occurred_at', '>=', $now->startOfDay())->count();
        $week = Alert::query()->where('occurred_at', '>=', $now->startOfWeek())->count();
        $month = Alert::query()->where('occurred_at', '>=', $now->startOfMonth())->count();

        $byType = Alert::query()
            ->select('type', DB::raw('count(*) as total'))
            ->where('occurred_at', '>=', $now->startOfMonth())
            ->groupBy('type')
            ->pluck('total', 'type');

        $criticalOpen = Alert::query()
            ->with(['vehicle'])
            ->where('status', AlertStatus::OPEN->value)
            ->whereIn('type', ['sos', 'jamming', 'offline'])
            ->orderByDesc('occurred_at')
            ->limit(20)
            ->get();

        return [
            'totals' => [
                'today' => $today,
                'week' => $week,
                'month' => $month,
            ],
            'by_type' => $byType,
            'critical_open' => $criticalOpen,
        ];
    }
}
