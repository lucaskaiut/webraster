<?php

namespace App\Modules\Poi\Http\Controllers;

use App\Modules\Client\Models\Client;
use App\Modules\Poi\Http\Requests\StorePoiRequest;
use App\Modules\Poi\Http\Requests\UpdatePoiRequest;
use App\Modules\Poi\Http\Resources\PoiCategoryResource;
use App\Modules\Poi\Http\Resources\PoiResource;
use App\Modules\Poi\Models\Poi;
use App\Modules\Poi\Services\PoiService;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoiController extends ApiController
{
    public function __construct(private readonly PoiService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Poi::class);

        $clientId = null;
        if ($request->filled('client_id')) {
            $clientId = Client::query()
                ->where('uuid', $request->string('client_id')->toString())
                ->value('id');
        }

        $isActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : null;

        $pois = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->string('search')->toString() ?: null,
            $clientId,
            $this->service->resolveCategoryId($request->string('category_id')->toString() ?: null),
            $isActive,
        );

        return $this->paginated(PoiResource::collection($pois));
    }

    public function map(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Poi::class);

        $clientId = null;
        if ($request->filled('client_id')) {
            $clientId = Client::query()
                ->where('uuid', $request->string('client_id')->toString())
                ->value('id');
        }

        $activeOnly = ! $request->has('is_active') || $request->boolean('is_active');

        $pois = $this->service->listForMap(
            $clientId,
            $this->service->resolveCategoryId($request->string('category_id')->toString() ?: null),
            $activeOnly,
        );

        return $this->success(PoiResource::collection($pois));
    }

    public function categories(): JsonResponse
    {
        $this->authorize('viewAny', Poi::class);

        return $this->success(PoiCategoryResource::collection($this->service->listCategories()));
    }

    public function show(Poi $poi): JsonResponse
    {
        $this->authorize('view', $poi);

        return $this->success(PoiResource::make($poi->load(['client', 'category'])));
    }

    public function store(StorePoiRequest $request): JsonResponse
    {
        $this->authorize('create', Poi::class);

        $poi = $this->service->create($request->validated());

        return $this->created(PoiResource::make($poi), 'POI criado com sucesso.');
    }

    public function update(UpdatePoiRequest $request, Poi $poi): JsonResponse
    {
        $this->authorize('update', $poi);

        $poi = $this->service->update($poi, $request->validated());

        return $this->success(PoiResource::make($poi), 'POI atualizado com sucesso.');
    }

    public function destroy(Poi $poi): JsonResponse
    {
        $this->authorize('delete', $poi);

        $this->service->delete($poi);

        return $this->success(null, 'POI removido com sucesso.');
    }
}
