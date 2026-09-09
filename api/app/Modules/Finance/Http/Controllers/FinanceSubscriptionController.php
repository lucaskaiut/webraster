<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Http\Resources\FinanceSubscriptionResource;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Finance\Services\FinanceSubscriptionService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceSubscriptionController extends ApiController
{
    public function __construct(private readonly FinanceSubscriptionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceSubscription::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        if ($request->filled('client_id')) {
            $filters['client_id'] = Client::query()->where('uuid', $request->string('client_id')->toString())->value('id');
        }

        $subscriptions = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $filters,
        );

        return $this->paginated(FinanceSubscriptionResource::collection($subscriptions));
    }

    public function show(FinanceSubscription $financeSubscription): JsonResponse
    {
        $this->authorize('view', $financeSubscription);

        $financeSubscription->load(['client', 'contract.plan']);

        return $this->success(FinanceSubscriptionResource::make($financeSubscription));
    }

    public function cancel(FinanceSubscription $financeSubscription): JsonResponse
    {
        $this->authorize('cancel', $financeSubscription);

        $subscription = $this->service->cancel($financeSubscription);

        return $this->success(FinanceSubscriptionResource::make($subscription), 'Assinatura cancelada.');
    }

    public function reactivate(FinanceSubscription $financeSubscription): JsonResponse
    {
        $this->authorize('reactivate', $financeSubscription);

        $subscription = $this->service->reactivate($financeSubscription);

        return $this->success(FinanceSubscriptionResource::make($subscription), 'Assinatura reativada.');
    }
}
