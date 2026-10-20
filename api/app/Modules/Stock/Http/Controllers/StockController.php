<?php

namespace App\Modules\Stock\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Stock\Http\Requests\StoreStockRequest;
use App\Modules\Stock\Http\Requests\UpdateStockRequest;
use App\Modules\Stock\Http\Resources\StockResource;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends ApiController
{
    public function __construct(private readonly StockService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Stock::class);

        $perPage = $request->string('per_page')->toString() === 'all'
            ? 'all'
            : (int) $request->integer('per_page', 15);

        $stocks = $this->service->paginate(
            $perPage,
            $request->string('search')->toString() ?: null,
            $request->has('is_active') ? $request->boolean('is_active') : null,
            $request->string('sort')->toString() ?: null,
        );

        return $this->paginated(StockResource::collection($stocks));
    }

    public function show(Stock $stock): JsonResponse
    {
        $this->authorize('view', $stock);

        return $this->success(StockResource::make($stock->loadCount(['equipments', 'technicians'])));
    }

    public function store(StoreStockRequest $request): JsonResponse
    {
        $this->authorize('create', Stock::class);

        $stock = $this->service->create($request->validated());

        return $this->created(StockResource::make($stock), 'Estoque criado com sucesso.');
    }

    public function update(UpdateStockRequest $request, Stock $stock): JsonResponse
    {
        $this->authorize('update', $stock);

        $stock = $this->service->update($stock, $request->validated());

        return $this->success(StockResource::make($stock), 'Estoque atualizado com sucesso.');
    }

    public function destroy(Stock $stock): JsonResponse
    {
        $this->authorize('delete', $stock);

        $this->service->delete($stock);

        return $this->success(null, 'Estoque removido com sucesso.');
    }
}
