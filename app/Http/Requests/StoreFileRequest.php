<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autenticação já é garantida pelo middleware auth:sanctum na rota;
        // não há regra extra de autorização para o upload em si.
        return true;
    }

    public function rules(): array
    {
        return [
            // "max" em regra de arquivo é em kilobytes: 10240 KB = 10 MB.
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}
