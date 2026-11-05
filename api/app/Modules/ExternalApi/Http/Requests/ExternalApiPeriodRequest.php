<?php

namespace App\Modules\ExternalApi\Http\Requests;

use App\Modules\ExternalApi\Services\ExternalApiMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExternalApiPeriodRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after:from'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'granularity' => ['nullable', Rule::in(ExternalApiMetricsService::GRANULARITIES)],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:80'],
            'service' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['success', 'error'])],
            'search' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function timezone(): string
    {
        return $this->validated('timezone') ?: (string) config('external_apis.display_timezone', 'America/Sao_Paulo');
    }

    public function to(): CarbonImmutable
    {
        $to = $this->validated('to');

        return $to ? CarbonImmutable::parse($to, $this->timezone()) : CarbonImmutable::now($this->timezone());
    }

    public function from(): CarbonImmutable
    {
        $from = $this->validated('from');

        return $from ? CarbonImmutable::parse($from, $this->timezone()) : $this->to()->subDay();
    }

    public function granularity(): string
    {
        return $this->validated('granularity') ?: 'hour';
    }

    /**
     * @return list<string>
     */
    public function services(): array
    {
        return array_values(array_filter((array) $this->validated('services', []), 'is_string'));
    }
}
