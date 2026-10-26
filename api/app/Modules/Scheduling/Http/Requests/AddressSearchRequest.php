<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Modules\Scheduling\Services\Geocoder;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AddressSearchRequest extends FormRequest
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
            'q' => [
                'required',
                'string',
                'max:200',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! Geocoder::hasMinimumWords($value)) {
                        $fail('Digite ao menos duas palavras para buscar o endereço.');
                    }
                },
            ],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:8'],
        ];
    }
}
