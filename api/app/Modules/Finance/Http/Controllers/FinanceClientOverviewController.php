<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Http\Resources\FinanceBillingResource;
use App\Modules\Finance\Http\Resources\FinancePlanResource;
use App\Modules\Finance\Http\Resources\FinanceSubscriptionResource;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Models\FinanceSubscription;
use App\Modules\Finance\Services\FinanceClientOverviewService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class FinanceClientOverviewController extends ApiController
{
    public function __construct(private readonly FinanceClientOverviewService $service) {}

    public function __invoke(Client $client): JsonResponse
    {
        $this->authorize('view', $client);
        $this->authorize('viewAny', FinanceSubscription::class);
        $this->authorize('viewAny', FinanceBilling::class);

        $overview = $this->service->forClient($client);

        return $this->success([
            'plan' => $overview['plan']
                ? FinancePlanResource::make($overview['plan'])->resolve()
                : null,
            'subscription' => $overview['subscription']
                ? FinanceSubscriptionResource::make($overview['subscription'])->resolve()
                : null,
            'open_billing' => $overview['open_billing']
                ? FinanceBillingResource::make($overview['open_billing'])->resolve()
                : null,
            'billings' => FinanceBillingResource::collection($overview['billings'])->resolve(),
        ]);
    }
}
