<?php

namespace App\Http\Requests;

class ActivateAnggotaRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'simpanan_pokok' => ['required', 'numeric', 'min:1'],
            'tanggal' => ['nullable', 'date'],
        ];
    }
}

