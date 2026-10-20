<?php

namespace App\Modules\Checklist\Http\Controllers;

use App\Modules\Checklist\Http\Requests\StoreChecklistRequest;
use App\Modules\Checklist\Http\Requests\UpdateChecklistRequest;
use App\Modules\Checklist\Http\Resources\ChecklistResource;
use App\Modules\Checklist\Models\Checklist;
use App\Modules\Checklist\Models\ServiceOrderChecklist;
use App\Modules\Checklist\Models\ServiceOrderChecklistPhoto;
use App\Modules\Checklist\Models\ServiceOrderChecklistQuestion;
use App\Modules\Checklist\Services\ChecklistService;
use App\Modules\Checklist\Services\ServiceOrderChecklistService;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChecklistController extends ApiController
{
    public function __construct(
        private readonly ChecklistService $service,
        private readonly ServiceOrderChecklistService $orderChecklists,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Checklist::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'type' => $request->string('type')->toString() ?: null,
            'sort' => $request->string('sort')->toString() ?: null,
        ];

        if ($request->has('is_active')) {
            $filters['is_active'] = $request->boolean('is_active');
        }

        $checklists = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $filters,
        );

        return $this->paginated(ChecklistResource::collection($checklists));
    }

    public function show(Checklist $checklist): JsonResponse
    {
        $this->authorize('view', $checklist);

        return $this->success(ChecklistResource::make($checklist->load('questions.options')));
    }

    public function store(StoreChecklistRequest $request): JsonResponse
    {
        $this->authorize('create', Checklist::class);

        $checklist = $this->service->create($request->validated());

        return $this->created(ChecklistResource::make($checklist), 'Checklist criado com sucesso.');
    }

    public function update(UpdateChecklistRequest $request, Checklist $checklist): JsonResponse
    {
        $this->authorize('update', $checklist);

        $checklist = $this->service->update($checklist, $request->validated());

        return $this->success(ChecklistResource::make($checklist), 'Checklist atualizado com sucesso.');
    }

    public function destroy(Checklist $checklist): JsonResponse
    {
        $this->authorize('delete', $checklist);

        $this->service->delete($checklist);

        return $this->success(null, 'Checklist removido com sucesso.');
    }

    public function downloadPhoto(
        ServiceOrder $serviceOrder,
        ServiceOrderChecklist $checklist,
        ServiceOrderChecklistQuestion $question,
        ServiceOrderChecklistPhoto $photo,
    ): StreamedResponse {
        $this->authorize('view', $serviceOrder);

        if (request()->user()?->client_id !== null) {
            abort(403);
        }

        return $this->orderChecklists->downloadPhotoForManager(
            $serviceOrder,
            $checklist,
            $question,
            $photo,
        );
    }
}
