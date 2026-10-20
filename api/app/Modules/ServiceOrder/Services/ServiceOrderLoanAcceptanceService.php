<?php

namespace App\Modules\ServiceOrder\Services;

use App\Modules\ServiceOrder\Enums\ServiceOrderHistoryAction;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Registra no próprio OS o aceite do termo de comodato assinado pelo cliente.
 * A imagem fica em disco privado e é servida apenas por rotas autenticadas.
 */
class ServiceOrderLoanAcceptanceService
{
    public const DISK = 'local';

    public function __construct(
        private readonly ServiceOrderHistoryService $history,
    ) {}

    public function store(ServiceOrder $order, UploadedFile $file, User $actor): ServiceOrder
    {
        if ($order->status !== ServiceOrderStatus::IN_PROGRESS) {
            throw ValidationException::withMessages([
                'status' => ['O aceite do termo de comodato só pode ser coletado com a OS em andamento.'],
            ]);
        }

        $tenant = $this->tenantFor($order);
        $term = trim((string) $tenant?->equipment_loan_term);

        if (! $tenant?->require_equipment_loan_acceptance) {
            throw ValidationException::withMessages([
                'loan_term_acceptance' => ['O aceite do termo de comodato não está habilitado para esta empresa.'],
            ]);
        }

        $termText = trim((string) preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($term), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ));

        if ($termText === '') {
            throw ValidationException::withMessages([
                'loan_term_acceptance' => ['A empresa não configurou o termo de comodato.'],
            ]);
        }

        $previous = $order->loan_term_signature_path;
        $path = $file->store("service-orders/{$order->uuid}/comodato", self::DISK);

        if ($path === false) {
            throw ValidationException::withMessages([
                'image' => ['Não foi possível salvar a assinatura.'],
            ]);
        }

        $order->forceFill([
            'loan_term_accepted_at' => now(),
            'loan_term_signature_path' => $path,
            'loan_term_collected_by' => $actor->getKey(),
            'loan_term_text' => $term,
            'loan_term_text_hash' => hash('sha256', $term),
        ])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk(self::DISK)->delete($previous);
        }

        $this->history->record(
            $order,
            ServiceOrderHistoryAction::LOAN_TERM_ACCEPTED,
            $actor,
            'loan_term_acceptance',
            null,
            'Aceite registrado',
            [
                'accepted_at' => $order->loan_term_accepted_at?->toIso8601String(),
                'term_hash' => $order->loan_term_text_hash,
                'signature_path' => $order->loan_term_signature_path,
            ],
        );

        return $order->refresh()->load('loanTermCollectedBy');
    }

    public function signature(ServiceOrder $order): StreamedResponse
    {
        $path = $order->loan_term_signature_path;

        if ($path === null) {
            abort(404);
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response(
            $path,
            sprintf('aceite-comodato-%s.png', $order->code),
            ['Content-Type' => $disk->mimeType($path) ?: 'image/png'],
            'inline',
        );
    }

    private function tenantFor(ServiceOrder $order): ?Tenant
    {
        $tenant = TenantContext::tenant();

        if ($tenant !== null && (int) $tenant->getKey() === (int) $order->tenant_id) {
            return $tenant;
        }

        return Tenant::query()->find($order->tenant_id);
    }
}
