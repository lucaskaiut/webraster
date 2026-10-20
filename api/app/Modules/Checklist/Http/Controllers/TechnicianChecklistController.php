<?php

namespace App\Modules\Checklist\Http\Controllers;

use App\Modules\Checklist\Http\Requests\StoreChecklistPhotoRequest;
use App\Modules\Checklist\Http\Requests\SubmitChecklistAnswersRequest;
use App\Modules\Checklist\Http\Resources\ServiceOrderChecklistPhotoResource;
use App\Modules\Checklist\Http\Resources\ServiceOrderChecklistResource;
use App\Modules\Checklist\Models\ServiceOrderChecklist;
use App\Modules\Checklist\Models\ServiceOrderChecklistPhoto;
use App\Modules\Checklist\Models\ServiceOrderChecklistQuestion;
use App\Modules\Checklist\Services\ServiceOrderChecklistService;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicianChecklistController extends ApiController
{
    public function __construct(private readonly ServiceOrderChecklistService $service) {}

    public function submit(
        SubmitChecklistAnswersRequest $request,
        ServiceOrder $serviceOrder,
        ServiceOrderChecklist $checklist,
    ): JsonResponse {
        $this->assertOwn($request, $serviceOrder);

        $checklist = $this->service->submit(
            $serviceOrder,
            $checklist,
            $request->validated('answers'),
            $request->user(),
        );

        return $this->success(
            ServiceOrderChecklistResource::make($checklist),
            'Checklist concluído com sucesso.',
        );
    }

    public function storePhoto(
        StoreChecklistPhotoRequest $request,
        ServiceOrder $serviceOrder,
        ServiceOrderChecklist $checklist,
        ServiceOrderChecklistQuestion $question,
    ): JsonResponse {
        $this->assertOwn($request, $serviceOrder);

        $photo = $this->service->addPhoto(
            $serviceOrder,
            $checklist,
            $question,
            $request->file('file'),
            $request->user(),
        );

        return $this->created(ServiceOrderChecklistPhotoResource::make($photo), 'Fotografia adicionada.');
    }

    public function destroyPhoto(
        Request $request,
        ServiceOrder $serviceOrder,
        ServiceOrderChecklist $checklist,
        ServiceOrderChecklistQuestion $question,
        ServiceOrderChecklistPhoto $photo,
    ): JsonResponse {
        $this->assertOwn($request, $serviceOrder);

        $this->service->deletePhoto($serviceOrder, $checklist, $question, $photo);

        return $this->success(null, 'Fotografia removida.');
    }

    public function downloadPhoto(Request $request, ServiceOrderChecklistPhoto $photo): StreamedResponse
    {
        return $this->service->downloadPhoto($photo, $request->user());
    }

    private function assertOwn(Request $request, ServiceOrder $order): void
    {
        if ((int) $order->technician_id !== (int) $request->user()->getKey()) {
            throw (new ModelNotFoundException)->setModel(ServiceOrder::class, [$order->uuid]);
        }
    }
}
