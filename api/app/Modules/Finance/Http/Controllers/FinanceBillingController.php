<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Http\Requests\ChargeFinanceBillingRequest;
use App\Modules\Finance\Http\Requests\GenerateFinanceBillingRequest;
use App\Modules\Finance\Http\Resources\FinanceBillingResource;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Finance\Services\BillingChargeService;
use App\Modules\Finance\Services\FinanceBillingService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceBillingController extends ApiController
{
    public function __construct(
        private readonly FinanceBillingService $service,
        private readonly BillingChargeService $chargeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceBilling::class);

        $billings = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            [
                'search' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'client_id' => $request->string('client_id')->toString() ?: null,
                'subscription_id' => $request->string('subscription_id')->toString() ?: null,
                'due_from' => $request->string('due_from')->toString() ?: null,
                'due_to' => $request->string('due_to')->toString() ?: null,
            ],
        );

        return $this->paginated(FinanceBillingResource::collection($billings));
    }

    public function show(FinanceBilling $financeBilling): JsonResponse
    {
        $this->authorize('view', $financeBilling);

        $financeBilling->load(['client', 'subscription.plan', 'events']);

        return $this->success(FinanceBillingResource::make($financeBilling));
    }

    public function store(GenerateFinanceBillingRequest $request): JsonResponse
    {
        $this->authorize('create', FinanceBilling::class);

        $data = $request->validated();
        $subscription = FinanceSubscription::query()->findOrFail($data['subscription_id']);
        $due = isset($data['due_at']) ? Carbon::parse($data['due_at']) : null;

        $billing = $this->service->generateForSubscription($subscription, $due);

        return $this->created(FinanceBillingResource::make($billing), 'Cobrança gerada.');
    }

    public function charge(ChargeFinanceBillingRequest $request, FinanceBilling $financeBilling): JsonResponse
    {
        $this->authorize('charge', $financeBilling);

        $data = $request->validated();
        $billing = $this->chargeService->charge(
            $financeBilling,
            PaymentMethod::from((string) $data['payment_method']),
            $data,
        );

        return $this->success(FinanceBillingResource::make($billing), 'Cobrança enviada ao gateway.');
    }

    public function cancel(FinanceBilling $financeBilling): JsonResponse
    {
        $this->authorize('cancel', $financeBilling);

        $billing = $this->service->cancel($financeBilling, request()->user());

        return $this->success(FinanceBillingResource::make($billing), 'Cobrança cancelada.');
    }

    public function markPaid(Request $request, FinanceBilling $financeBilling): JsonResponse
    {
        $this->authorize('update', $financeBilling);

        $paidAmount = $request->filled('paid_amount_cents')
            ? (int) $request->integer('paid_amount_cents')
            : null;

        $billing = $this->service->markPaid($financeBilling, $paidAmount);

        return $this->success(FinanceBillingResource::make($billing), 'Cobrança liquidada.');
    }
}
