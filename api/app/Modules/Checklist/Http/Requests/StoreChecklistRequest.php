<?php

namespace App\Modules\Checklist\Http\Requests;

use App\Modules\Checklist\Http\Requests\Concerns\ValidatesChecklist;
use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistRequest extends FormRequest
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
        return $this->checklistRules();
    }
}
