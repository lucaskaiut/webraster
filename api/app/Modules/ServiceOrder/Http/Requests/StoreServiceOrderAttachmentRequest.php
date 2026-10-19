<?php

namespace App\Modules\ServiceOrder\Http\Requests;

use App\Modules\ServiceOrder\Services\ServiceOrderAttachmentService;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceOrderAttachmentRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'extensions:'.implode(',', ServiceOrderAttachmentService::ALLOWED_EXTENSIONS),
                'max:'.ServiceOrderAttachmentService::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Formato não permitido. Envie PDF, imagem, Word, Excel ou texto.',
            'file.max' => 'O arquivo deve ter no máximo 10 MB.',
        ];
    }
}
