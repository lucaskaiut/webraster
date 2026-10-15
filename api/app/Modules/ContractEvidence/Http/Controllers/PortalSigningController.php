<?php

namespace App\Modules\ContractEvidence\Http\Controllers;

use App\Modules\Client\Http\Resources\ClientContractResource;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Services\ClientContractPdfService;
use App\Modules\Client\Services\ClientContractService;
use App\Modules\ContractEvidence\Exceptions\SigningException;
use App\Modules\ContractEvidence\Http\Requests\CompleteSigningSessionRequest;
use App\Modules\ContractEvidence\Http\Resources\ContractSignatureResource;
use App\Modules\ContractEvidence\Http\Resources\SigningSessionResource;
use App\Modules\ContractEvidence\Models\ContractSigningSession;
use App\Modules\ContractEvidence\Services\ContractSigningService;
use App\Modules\ContractEvidence\Support\RequestEvidence;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Assinatura pelo próprio cliente no app. O cliente e o contrato vêm da
 * conta autenticada; o app não informa quem assina nem qual contrato.
 */
class PortalSigningController extends ApiController
{
    public function __construct(
        private readonly ContractSigningService $signing,
        private readonly ClientContractService $contracts,
        private readonly ClientContractPdfService $pdfs,
    ) {}

    public function start(Request $request): JsonResponse
    {
        [$user, $client] = $this->portalUser($request);

        $session = $this->signing->start($user, $client, RequestEvidence::fromRequest($request));
        $session->load('clientContract.contract');

        return $this->created(
            new SigningSessionResource($session, $this->signing->presentedDocument($session)),
            'Sessão de assinatura iniciada.',
        );
    }

    public function complete(CompleteSigningSessionRequest $request, string $session): JsonResponse
    {
        [$user, $client] = $this->portalUser($request);

        $signingSession = ContractSigningSession::query()
            ->where('uuid', $session)
            ->where('user_id', $user->getKey())
            ->where('client_id', $client->getKey())
            ->first();

        if ($signingSession === null) {
            throw new SigningException('session_not_found', 'Sessão de assinatura não encontrada.', 404);
        }

        $context = RequestEvidence::fromRequest($request);

        [$signature, $idempotent] = $this->signing->complete($user, $signingSession, [
            'image' => $request->file('image'),
            'signer_name' => $request->validated('signer_name'),
            'signer_cpf' => $request->validated('signer_cpf'),
            'signer_birth_date' => $request->validated('signer_birth_date'),
            'consent_accepted' => $request->boolean('consent_accepted'),
            'consent_sha256' => $request->validated('consent_sha256'),
            'document_sha256' => $request->validated('document_sha256'),
        ], $context);

        return $this->success([
            'contract' => ClientContractResource::make($this->contracts->currentForClient($client)),
            'signature' => ContractSignatureResource::make($signature),
            'idempotent' => $idempotent,
        ], 'Contrato assinado com sucesso.')->header('X-Request-Id', $context->requestId);
    }

    public function document(Request $request): StreamedResponse
    {
        [, $client] = $this->portalUser($request);

        $contract = $this->contracts->currentForClient($client);

        abort_if($contract === null, 404, 'Nenhum contrato vinculado a este cliente.');

        return $this->pdfs->download($contract, 'contrato.pdf');
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function portalUser(Request $request): array
    {
        $user = $request->user();
        $client = $user instanceof User ? $user->client : null;

        abort_if($client === null, 403, 'Acesso restrito a usuários do portal do cliente.');

        $this->authorize('signContract', $client);

        return [$user, $client];
    }
}
