<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Http\Requests\ChargeFinanceBillingRequest;
use App\Modules\Finance\Http\Resources\FinanceBillingResource;
use App\Modules\Finance\Http\Resources\FinanceSubscriptionResource;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Finance\Services\BillingChargeService;
use App\Modules\Finance\Services\FinanceBillingService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancePortalController extends ApiController
{
    public function __construct(
        private readonly FinanceBillingService $billings,
        private readonly BillingChargeService $chargeService,
    ) {}

    public function subscription(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceSubscription::class);

        $subscription = FinanceSubscription::query()
            ->with(['client', 'plan'])
            ->orderByDesc('created_at')
            ->first();

        if ($subscription === null) {
            return $this->success(null, 'Nenhuma assinatura encontrada.');
        }

        $this->authorize('view', $subscription);

        return $this->success(FinanceSubscriptionResource::make($subscription));
    }

    public function billings(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceBilling::class);

        $items = $this->billings->paginate(
            (int) $request->integer('per_page', 15),
            [
                'status' => $request->string('status')->toString() ?: null,
                'due_from' => $request->string('due_from')->toString() ?: null,
                'due_to' => $request->string('due_to')->toString() ?: null,
            ],
        );

        return $this->paginated(FinanceBillingResource::collection($items));
    }

    public function showBilling(FinanceBilling $financeBilling): JsonResponse
    {
        $this->authorize('view', $financeBilling);

        $financeBilling->load(['client', 'subscription.plan']);

        return $this->success(FinanceBillingResource::make($financeBilling));
    }

    public function pay(ChargeFinanceBillingRequest $request, FinanceBilling $financeBilling): JsonResponse
    {
        $this->authorize('pay', $financeBilling);

        $data = $request->validated();
        $billing = $this->chargeService->charge(
            $financeBilling,
            PaymentMethod::from((string) $data['payment_method']),
            $data,
        );

        return $this->success(FinanceBillingResource::make($billing), 'Pagamento iniciado.');
    }
}
