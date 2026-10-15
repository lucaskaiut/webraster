<?php

namespace App\Modules\ContractEvidence\Http\Controllers;

use App\Modules\Client\Models\Client;
use App\Modules\ContractEvidence\Http\Resources\ContractSignatureResource;
use App\Modules\ContractEvidence\Models\ContractEvidenceEvent;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Services\EvidencePackageExporter;
use App\Modules\ContractEvidence\Services\Verification\EvidenceVerifier;
use App\Modules\ContractEvidence\Support\RequestEvidence;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Consulta, verificação e exportação das evidências pela equipe.
 */
class ContractEvidenceController extends ApiController
{
    public function __construct(
        private readonly EvidenceVerifier $verifier,
        private readonly EvidencePackageExporter $exporter,
    ) {}

    public function index(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        $signatures = ContractSignature::query()
            ->where('client_id', $client->getKey())
            ->orderByDesc('id')
            ->get();

        $events = ContractEvidenceEvent::query()
            ->whereIn('client_contract_id', $signatures->pluck('client_contract_id')->unique())
            ->orderBy('sequence')
            ->get(['uuid', 'sequence', 'type', 'result', 'occurred_at', 'ip', 'app_version', 'data', 'hash']);

        return $this->success([
            'signatures' => ContractSignatureResource::collection($signatures),
            'events' => $events->map(fn (ContractEvidenceEvent $event) => [
                'id' => $event->uuid,
                'sequence' => $event->sequence,
                'type' => $event->type,
                'result' => $event->result,
                'occurred_at' => $event->occurred_at?->toIso8601ZuluString('microsecond'),
                'ip' => $event->ip,
                'app_version' => $event->app_version,
                'data' => $event->data,
                'hash' => $event->hash,
            ]),
        ]);
    }

    public function verify(Client $client, string $signature): JsonResponse
    {
        $this->authorize('view', $client);

        return $this->success($this->verifier->verifyStored($this->find($client, $signature))->toArray());
    }

    public function export(Request $request, Client $client, string $signature): BinaryFileResponse
    {
        $this->authorize('view', $client);

        $record = $this->find($client, $signature);
        $path = $this->exporter->export($record, RequestEvidence::fromRequest($request), $request->user()?->getKey());

        return response()
            ->download($path, "evidencias-{$record->uuid}.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    private function find(Client $client, string $uuid): ContractSignature
    {
        return ContractSignature::query()
            ->where('client_id', $client->getKey())
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
