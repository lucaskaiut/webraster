<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Modules\Scheduling\Http\Requests\AddressSearchRequest;
use App\Modules\Scheduling\Services\Geocoder;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class AddressSearchController extends ApiController
{
    public function __construct(private readonly Geocoder $geocoder) {}

    /**
     * Sugestões de endereço para o autocomplete do formulário de OS.
     */
    public function __invoke(AddressSearchRequest $request): JsonResponse
    {
        $limit = (int) ($request->validated('limit') ?? 6);

        return $this->success(
            $this->geocoder->search((string) $request->validated('q'), $limit),
        );
    }
}
