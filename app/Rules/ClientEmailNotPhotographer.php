<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class ClientEmailNotPhotographer implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $isPhotographerEmail = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower(trim((string) $value))])
            ->where('is_client_account', false)
            ->exists();

        if ($isPhotographerEmail) {
            $fail('Este e-mail já pertence a uma conta de fotógrafo.');
        }
    }
}
