<?php

namespace App\Modules\Client\Http\Requests;

use App\Modules\Client\Enums\ContractSignatureStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientContractSignatureRequest extends FormRequest
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
            'signature_status' => ['required', Rule::enum(ContractSignatureStatus::class)],
        ];
    }
}
