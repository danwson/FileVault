<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShareLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A checagem de dono do arquivo é feita via FilePolicy no
        // controller (precisa do model resolvido pela rota primeiro).
        return true;
    }

    public function rules(): array
    {
        return [
            'expires_in_minutes' => ['required', 'integer', 'min:1', 'max:44640'], // até 31 dias
            'max_uses' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
