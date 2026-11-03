<?php

namespace App\Modules\Tracking\Http\Requests;

use App\Modules\Tracking\Models\TrackingShareLink;
use Illuminate\Foundation\Http\FormRequest;

class StoreTrackingShareLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vehicle_ids' => ['required', 'array', 'min:1', 'max:50'],
            'vehicle_ids.*' => ['required', 'uuid', 'distinct'],
            'expires_in_hours' => ['required', 'integer', 'min:1', 'max:'.TrackingShareLink::MAX_HOURS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehicle_ids' => 'veículos',
            'expires_in_hours' => 'validade',
        ];
    }
}
