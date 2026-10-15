<?php

namespace App\Modules\ContractEvidence\Services;

use App\Modules\Client\Models\ClientContract;
use App\Modules\ContractEvidence\Enums\EvidenceEventType;
use App\Modules\ContractEvidence\Models\ContractEvidenceEvent;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Models\ContractSigningSession;
use App\Modules\ContractEvidence\Support\CanonicalJson;
use App\Modules\ContractEvidence\Support\RequestEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trilha de evidências append-only. Cada evento registra o hash do anterior
 * (cadeia por vínculo de contrato), então remoção, reordenação ou alteração
 * de qualquer evento quebra a verificação dos seguintes.
 */
class EvidenceLedger
{
    private const ATTEMPTS = 3;

    /**
     * @param  array<string, mixed>  $data  sem CPF completo, tokens ou segredos
     */
    public function record(
        ClientContract $contract,
        EvidenceEventType $type,
        array $data = [],
        ?RequestEvidence $context = null,
        ?int $userId = null,
        ?ContractSigningSession $session = null,
        ?ContractSignature $signature = null,
        string $result = 'success',
    ): ContractEvidenceEvent {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => $this->append(
                    $contract, $type, $data, $context, $userId, $session, $signature, $result,
                ));
            } catch (QueryException $e) {
                // Corrida na sequência (índice único): relê o topo da cadeia.
                if ($attempt >= self::ATTEMPTS || ! $this->isUniqueViolation($e)) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Hash canônico de um evento, recalculável a partir apenas da linha.
     *
     * @param  array<string, mixed>  $event
     */
    public static function hashOf(array $event): string
    {
        return CanonicalJson::hash([
            'v' => 1,
            'uuid' => $event['uuid'],
            'tenant_id' => (int) $event['tenant_id'],
            'client_contract_id' => (int) $event['client_contract_id'],
            'client_contract_uuid' => $event['client_contract_uuid'],
            'sequence' => (int) $event['sequence'],
            'type' => $event['type'],
            'result' => $event['result'],
            'occurred_at' => $event['occurred_at'],
            'session_id' => $event['session_id'] !== null ? (int) $event['session_id'] : null,
            'signature_id' => $event['signature_id'] !== null ? (int) $event['signature_id'] : null,
            'user_id' => $event['user_id'] !== null ? (int) $event['user_id'] : null,
            'request_id' => $event['request_id'],
            'ip' => $event['ip'],
            'user_agent' => $event['user_agent'],
            'app_version' => $event['app_version'],
            'data' => $event['data'] ?: null,
            'previous_hash' => $event['previous_hash'],
        ]);
    }

    /**
     * Representação exportável (e usada no hash) de um evento persistido.
     *
     * @return array<string, mixed>
     */
    public static function export(ContractEvidenceEvent $event): array
    {
        return [
            'uuid' => $event->uuid,
            'tenant_id' => (int) $event->tenant_id,
            'client_contract_id' => (int) $event->client_contract_id,
            'client_contract_uuid' => $event->client_contract_uuid,
            'sequence' => (int) $event->sequence,
            'type' => $event->type,
            'result' => $event->result,
            'occurred_at' => CanonicalJson::timestamp($event->occurred_at),
            'session_id' => $event->session_id,
            'signature_id' => $event->signature_id,
            'user_id' => $event->user_id,
            'request_id' => $event->request_id,
            'ip' => $event->ip,
            'user_agent' => $event->user_agent,
            'app_version' => $event->app_version,
            'data' => $event->data ?: null,
            'previous_hash' => $event->previous_hash,
            'hash' => $event->hash,
        ];
    }

    private function append(
        ClientContract $contract,
        EvidenceEventType $type,
        array $data,
        ?RequestEvidence $context,
        ?int $userId,
        ?ContractSigningSession $session,
        ?ContractSignature $signature,
        string $result,
    ): ContractEvidenceEvent {
        // Serializa a escrita da cadeia deste contrato (no-op no sqlite).
        ClientContract::withoutGlobalScopes()->whereKey($contract->getKey())->lockForUpdate()->value('id');

        $last = ContractEvidenceEvent::withoutGlobalScopes()
            ->where('client_contract_id', $contract->getKey())
            ->orderByDesc('sequence')
            ->first(['sequence', 'hash']);

        $attributes = [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => (int) $contract->tenant_id,
            'client_contract_id' => (int) $contract->getKey(),
            'client_contract_uuid' => (string) $contract->uuid,
            'sequence' => ($last?->sequence ?? 0) + 1,
            'type' => $type->value,
            'result' => $result,
            'occurred_at' => CanonicalJson::timestamp(CarbonImmutable::now('UTC')),
            'session_id' => $session?->getKey(),
            'signature_id' => $signature?->getKey(),
            'user_id' => $userId,
            'request_id' => $context?->requestId,
            'ip' => $context?->ip,
            'user_agent' => $context?->userAgent,
            'app_version' => $context?->appVersion,
            'data' => $data === [] ? null : $data,
            'previous_hash' => $last?->hash,
        ];

        $attributes['hash'] = self::hashOf($attributes);
        $attributes['occurred_at'] = CarbonImmutable::parse($attributes['occurred_at']);

        return ContractEvidenceEvent::withoutGlobalScopes()->create($attributes);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) $e->getCode();

        return in_array($code, ['23000', '23505'], true) || str_contains($e->getMessage(), 'UNIQUE');
    }
}
