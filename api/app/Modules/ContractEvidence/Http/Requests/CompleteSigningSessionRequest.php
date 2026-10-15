<?php

namespace App\Modules\ContractEvidence\Http\Requests;

use App\Modules\Shared\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;

class CompleteSigningSessionRequest extends FormRequest
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
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'signer_name' => ['required', 'string', 'max:255'],
            'signer_cpf' => ['required', 'string', new Cpf],
            'signer_birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'consent_accepted' => ['required', 'accepted'],
            'consent_sha256' => ['required', 'string', 'size:64'],
            'document_sha256' => ['required', 'string', 'size:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent_accepted.required' => 'É necessário aceitar o termo de consentimento para assinar.',
            'consent_accepted.accepted' => 'É necessário aceitar o termo de consentimento para assinar.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('signer_cpf')) {
            $this->merge([
                'signer_cpf' => preg_replace('/\D+/', '', (string) $this->input('signer_cpf')),
            ]);
        }
    }
}
