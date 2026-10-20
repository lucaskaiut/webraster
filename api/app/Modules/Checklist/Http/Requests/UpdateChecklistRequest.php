<?php

namespace App\Modules\Checklist\Http\Requests;

use App\Modules\Checklist\Http\Requests\Concerns\ValidatesChecklist;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistRequest extends FormRequest
{
    use ValidatesChecklist;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->checklistRules();
        $rules['name'][0] = 'sometimes';
        $rules['type'][0] = 'sometimes';

        return $rules;
    }
}
