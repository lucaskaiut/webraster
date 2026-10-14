<?php

namespace App\Modules\Service\Http\Requests\Concerns;

use App\Modules\Service\Models\Service;
use Illuminate\Validation\Rule;

trait ResolvesLinkedServices
{
    /**
     * @return array<string, mixed>
     */
    protected function linkedServiceRules(?int $ignoreServiceId = null): array
    {
        $itemRules = ['required', 'integer', 'distinct', Rule::exists('services', 'id')->whereNull('deleted_at')];

        if ($ignoreServiceId !== null) {
            $itemRules[] = Rule::notIn([$ignoreServiceId]);
        }

        return [
            'linked_service_ids' => ['sometimes', 'array', 'max:50'],
            'linked_service_ids.*' => $itemRules,
        ];
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('linked_service_ids');

        if (! is_array($ids)) {
            return;
        }

        $keys = array_values(array_filter($ids, fn ($id) => is_string($id) || is_int($id)));

        // Consulta com escopo de tenant: chaves de outro tenant resolvem para null e falham na validação.
        $services = $keys === []
            ? collect()
            : Service::query()
                ->where(fn ($query) => $query
                    ->whereIn('uuid', array_map('strval', $keys))
                    ->orWhereIn('id', array_filter($keys, 'is_numeric')))
                ->get(['id', 'uuid']);

        $byUuid = $services->pluck('id', 'uuid');
        $byId = $services->pluck('id', 'id');

        $this->merge([
            'linked_service_ids' => array_map(
                fn ($id) => is_numeric($id) ? $byId->get((int) $id) : $byUuid->get($id),
                $ids,
            ),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'linked_service_ids.*.required' => 'Serviço vinculado inválido.',
            'linked_service_ids.*.exists' => 'Serviço vinculado inválido.',
            'linked_service_ids.*.not_in' => 'O serviço não pode ser vinculado a ele mesmo.',
            'linked_service_ids.*.distinct' => 'Serviço vinculado duplicado.',
        ];
    }
}
