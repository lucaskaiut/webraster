<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Http\Requests\ChargeFinanceReceivableRequest;
use App\Modules\Finance\Http\Requests\GenerateFinanceReceivableRequest;
use App\Modules\Finance\Http\Resources\FinanceReceivableResource;
use App\Modules\Finance\Models\FinanceContract;
use App\Modules\Finance\Models\FinanceReceivable;
use App\Modules\Finance\Services\FinanceReceivableService;
use App\Modules\Finance\Services\ReceivableChargeService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceReceivableController extends ApiController
{
    public function __construct(
        private readonly FinanceReceivableService $service,
        private readonly ReceivableChargeService $chargeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceReceivable::class);

        $receivables = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            [
                'search' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'client_id' => $request->string('client_id')->toString() ?: null,
                'contract_id' => $request->string('contract_id')->toString() ?: null,
                'due_from' => $request->string('due_from')->toString() ?: null,
                'due_to' => $request->string('due_to')->toString() ?: null,
            ],
        );

        return $this->paginated(FinanceReceivableResource::collection($receivables));
    }

    public function show(FinanceReceivable $financeReceivable): JsonResponse
    {
        $this->authorize('view', $financeReceivable);

        $financeReceivable->load(['client', 'contract.plan', 'subscription', 'events']);

        return $this->success(FinanceReceivableResource::make($financeReceivable));
    }

    public function store(GenerateFinanceReceivableRequest $request): JsonResponse
    {
        $this->authorize('create', FinanceReceivable::class);

        $data = $request->validated();
        $contract = FinanceContract::query()->findOrFail($data['contract_id']);
        $due = isset($data['due_at']) ? Carbon::parse($data['due_at']) : null;

        $receivable = $this->service->generateForContract($contract, $due);

        return $this->created(FinanceReceivableResource::make($receivable), 'Cobrança gerada.');
    }

    public function charge(ChargeFinanceReceivableRequest $request, FinanceReceivable $financeReceivable): JsonResponse
    {
        $this->authorize('charge', $financeReceivable);

        $data = $request->validated();
        $creditCard = [];
        if (! empty($data['credit_card'])) {
            $creditCard['creditCard'] = $data['credit_card'];
        }
        if (! empty($data['creditCardHolderInfo'])) {
            $creditCard['creditCardHolderInfo'] = $data['creditCardHolderInfo'];
        }

        $receivable = $this->chargeService->charge(
            $financeReceivable,
            PaymentMethod::from((string) $data['payment_method']),
            $creditCard,
        );

        return $this->success(FinanceReceivableResource::make($receivable), 'Cobrança enviada ao gateway.');
    }

    public function cancel(FinanceReceivable $financeReceivable): JsonResponse
    {
        $this->authorize('cancel', $financeReceivable);

        $receivable = $this->service->cancel($financeReceivable, request()->user());

        return $this->success(FinanceReceivableResource::make($receivable), 'Cobrança cancelada.');
    }

    public function markReceived(Request $request, FinanceReceivable $financeReceivable): JsonResponse
    {
        $this->authorize('update', $financeReceivable);

        $paidAmount = $request->filled('paid_amount_cents')
            ? (int) $request->integer('paid_amount_cents')
            : null;

        $receivable = $this->service->markReceived($financeReceivable, $paidAmount);

        return $this->success(FinanceReceivableResource::make($receivable), 'Cobrança liquidada.');
    }
}
