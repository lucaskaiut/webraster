<?php

namespace App\Modules\ServiceOrder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceOrderPhotoRequest extends FormRequest
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
            'path' => ['required', 'string', 'max:255'],
        ];
    }
}
