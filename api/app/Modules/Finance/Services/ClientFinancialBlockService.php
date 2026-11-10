<?php

namespace App\Modules\Finance\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Support\Facades\ClientContext;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceCollectionSetting;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;

/**
 * Bloqueio por inadimplência: com a opção ligada na régua de cobrança, o
 * usuário do portal cujo cliente tem cobrança em aberto vencida há N dias ou
 * mais só acessa o financeiro. O pagamento libera o acesso na hora.
 *
 * O resultado é calculado uma vez por requisição (cache atrelado à
 * requisição atual, seguro também em workers de longa duração).
 */
class ClientFinancialBlockService
{
    /** Status em que a cobrança ainda está em aberto. */
    private const OPEN_STATUSES = [
        BillingStatus::PENDING,
        BillingStatus::AWAITING_PAYMENT,
        BillingStatus::OVERDUE,
    ];

    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    private ?int $cachedRequest = null;

    /**
     * Situação do usuário autenticado no portal; null quando não se aplica
     * (usuário da empresa, sem cliente ou fora do contexto de empresa).
     *
     * @return array<string, mixed>|null
     */
    public function forUser(User $user): ?array
    {
        if ($user->client_id === null || ! TenantContext::isResolved() || ! ClientContext::isResolved()) {
            return null;
        }

        $client = ClientContext::client();

        if ($client === null || (int) $client->getKey() !== (int) $user->client_id) {
            return null;
        }

        return $this->forClient($client);
    }

    public function isBlocked(User $user): bool
    {
        return (bool) ($this->forUser($user)['blocked'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function forClient(Client $client): array
    {
        $key = (int) $client->getKey();
        $request = app()->bound('request') ? spl_object_id(app('request')) : null;

        if ($request !== $this->cachedRequest) {
            $this->cache = [];
            $this->cachedRequest = $request;
        }

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $setting = FinanceCollectionSetting::query()->first();

        if ($setting === null || ! $setting->block_access_enabled) {
            return $this->cache[$key] = $this->unblocked();
        }

        $today = CarbonImmutable::now((string) config('scheduling.timezone'))->startOfDay();
        $limit = $today->subDays(max(1, $setting->block_access_after_days));

        $overdue = FinanceBilling::query()
            ->withoutClientScope()
            ->where('client_id', $key)
            ->whereIn('status', array_map(fn (BillingStatus $status) => $status->value, self::OPEN_STATUSES))
            ->whereDate('due_at', '<=', $limit->toDateString())
            ->orderBy('due_at')
            ->get(['id', 'uuid', 'number', 'due_at', 'amount_cents', 'discount_cents', 'fine_cents', 'interest_cents']);

        if ($overdue->isEmpty()) {
            return $this->cache[$key] = $this->unblocked();
        }

        $oldest = $overdue->first();

        return $this->cache[$key] = [
            'blocked' => true,
            'message' => 'Seu acesso está restrito ao financeiro por causa de cobranças em atraso. Após o pagamento, o acesso é liberado automaticamente.',
            'overdue_count' => $overdue->count(),
            'overdue_total_cents' => (int) $overdue->sum(fn (FinanceBilling $billing) => $billing->totalCents()),
            'oldest_due_at' => $oldest?->due_at?->toDateString(),
            'billing_id' => $oldest?->uuid,
        ];
    }

    public function forget(): void
    {
        $this->cache = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function unblocked(): array
    {
        return [
            'blocked' => false,
            'message' => null,
            'overdue_count' => 0,
            'overdue_total_cents' => 0,
            'oldest_due_at' => null,
            'billing_id' => null,
        ];
    }
}
