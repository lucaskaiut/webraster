<?php

namespace App\Modules\Tenant\Http\Requests;

use App\Modules\Tenant\Enums\ThemeFont;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantThemeRequest extends FormRequest
{
    private const HEX = 'regex:/^#[0-9a-fA-F]{6}$/';

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
            'primary_color' => ['present', 'nullable', 'string', self::HEX],
            'secondary_color' => ['present', 'nullable', 'string', self::HEX],
            'font_family' => ['present', 'nullable', 'string', Rule::enum(ThemeFont::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'Informe a cor no formato #RRGGBB.',
            'secondary_color.regex' => 'Informe a cor no formato #RRGGBB.',
            'font_family.enum' => 'Fonte inválida.',
        ];
    }

    /**
     * @return array{primary_color: ?string, secondary_color: ?string, font_family: ?string}
     */
    public function theme(): array
    {
        $data = $this->validated();

        return [
            'primary_color' => isset($data['primary_color']) ? strtoupper($data['primary_color']) : null,
            'secondary_color' => isset($data['secondary_color']) ? strtoupper($data['secondary_color']) : null,
            'font_family' => $data['font_family'] ?? null,
        ];
    }
}
