<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Http\Requests\ChargeFinanceReceivableRequest;
use App\Modules\Finance\Http\Resources\FinanceReceivableResource;
use App\Modules\Finance\Http\Resources\FinanceSubscriptionResource;
use App\Modules\Finance\Models\FinanceReceivable;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Finance\Services\FinanceReceivableService;
use App\Modules\Finance\Services\ReceivableChargeService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancePortalController extends ApiController
{
    public function __construct(
        private readonly FinanceReceivableService $receivables,
        private readonly ReceivableChargeService $chargeService,
    ) {}

    public function subscription(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceSubscription::class);

        $subscription = FinanceSubscription::query()
            ->with(['client', 'contract.plan'])
            ->orderByDesc('created_at')
            ->first();

        if ($subscription === null) {
            return $this->success(null, 'Nenhuma assinatura encontrada.');
        }

        $this->authorize('view', $subscription);

        return $this->success(FinanceSubscriptionResource::make($subscription));
    }

    public function receivables(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceReceivable::class);

        $items = $this->receivables->paginate(
            (int) $request->integer('per_page', 15),
            [
                'status' => $request->string('status')->toString() ?: null,
                'due_from' => $request->string('due_from')->toString() ?: null,
                'due_to' => $request->string('due_to')->toString() ?: null,
            ],
        );

        return $this->paginated(FinanceReceivableResource::collection($items));
    }

    public function showReceivable(FinanceReceivable $financeReceivable): JsonResponse
    {
        $this->authorize('view', $financeReceivable);

        $financeReceivable->load(['client', 'contract.plan', 'subscription']);

        return $this->success(FinanceReceivableResource::make($financeReceivable));
    }

    public function pay(ChargeFinanceReceivableRequest $request, FinanceReceivable $financeReceivable): JsonResponse
    {
        $this->authorize('pay', $financeReceivable);

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

        return $this->success(FinanceReceivableResource::make($receivable), 'Pagamento iniciado.');
    }
}
