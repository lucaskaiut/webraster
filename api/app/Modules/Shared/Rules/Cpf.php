<?php

namespace App\Modules\Shared\Rules;

use App\Modules\Shared\Support\Document;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Document::isValidCpf($value)) {
            $fail('O campo :attribute deve ser um CPF válido.');
        }
    }
}
