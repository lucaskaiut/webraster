<?php

namespace App\Modules\DeviceCommand\Http\Controllers;

use App\Modules\DeviceCommand\Http\Requests\SendDeviceCommandRequest;
use App\Modules\DeviceCommand\Http\Resources\DeviceCommandLogResource;
use App\Modules\DeviceCommand\Policies\DeviceCommandPolicy;
use App\Modules\DeviceCommand\Services\DeviceCommandService;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Shared\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class DeviceCommandController extends ApiController
{
    public function __construct(private readonly DeviceCommandService $service) {}

    public function index(Equipment $device): JsonResponse
    {
        $this->ensureCan('viewCommands', $device);

        $commands = $this->service->availableCommands($device);

        return $this->success($commands->all());
    }

    public function store(SendDeviceCommandRequest $request, Equipment $device): JsonResponse
    {
        $this->ensureCan('send', $device);

        $data = $request->validated();
        $log = $this->service->send(
            $device,
            (string) $data['type'],
            is_array($data['attributes'] ?? null) ? $data['attributes'] : [],
            $request->user(),
        );

        $log->loadMissing(['vehicle', 'equipment']);

        return $this->success(
            DeviceCommandLogResource::make($log),
            'Comando enviado.',
        );
    }

    private function ensureCan(string $ability, Equipment $device): void
    {
        $user = request()->user();
        $policy = app(DeviceCommandPolicy::class);
        $allowed = match ($ability) {
            'viewCommands' => $policy->viewCommands($user, $device),
            'send' => $policy->send($user, $device),
            default => false,
        };

        abort_unless($allowed, 403);
    }
}
