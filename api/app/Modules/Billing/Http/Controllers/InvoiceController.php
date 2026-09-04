<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Http\Requests\InitiateInvoicePaymentRequest;
use App\Modules\Billing\Http\Resources\InvoiceResource;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends ApiController
{
    public function __construct(
        private readonly BillingService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        return $this->paginated(
            InvoiceResource::collection(
                $this->service->paginate((int) $request->integer('per_page', 15))
            ),
        );
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return $this->success(
            InvoiceResource::make($invoice->load('subscription.plan')),
        );
    }

    public function pay(InitiateInvoicePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('pay', $invoice);

        $paid = $this->service->initiatePayment(
            $invoice,
            $request->validated('payment_gateway'),
            $request->validated('payment_data') ?? [],
        );

        return $this->success(
            InvoiceResource::make($paid->load('subscription.plan')),
            'Cobrança iniciada com sucesso.',
        );
    }
}
