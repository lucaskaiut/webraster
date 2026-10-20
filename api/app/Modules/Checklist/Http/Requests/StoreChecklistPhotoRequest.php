<?php

namespace App\Modules\Checklist\Http\Requests;

use App\Modules\Checklist\Services\ServiceOrderChecklistService;
use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistPhotoRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,heic',
                'max:'.ServiceOrderChecklistService::MAX_PHOTO_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Envie uma imagem JPG, PNG, WEBP ou HEIC.',
            'file.max' => 'A fotografia pode ter no máximo 10 MB.',
        ];
    }
}
