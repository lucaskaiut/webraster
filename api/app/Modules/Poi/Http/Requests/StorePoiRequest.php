<?php

namespace App\Modules\Poi\Http\Requests;

use App\Modules\Client\Models\Client;
use App\Modules\Poi\Models\PoiCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePoiRequest extends FormRequest
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
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'poi_category_id' => ['required', 'integer', Rule::exists('poi_categories', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->filled('client_id') && ! is_numeric($this->input('client_id'))) {
            $client = Client::query()->where('uuid', $this->input('client_id'))->first();
            $input['client_id'] = $client?->getKey();
        }

        if ($this->filled('poi_category_id') && ! is_numeric($this->input('poi_category_id'))) {
            $category = PoiCategory::query()->where('uuid', $this->input('poi_category_id'))->first();
            $input['poi_category_id'] = $category?->getKey();
        }

        if ($this->filled('category_id') && ! $this->filled('poi_category_id')) {
            $value = $this->input('category_id');
            if (is_numeric($value)) {
                $input['poi_category_id'] = (int) $value;
            } else {
                $category = PoiCategory::query()->where('uuid', $value)->first();
                $input['poi_category_id'] = $category?->getKey();
            }
        }

        if ($input !== []) {
            $this->merge($input);
        }
    }
}
