<?php

namespace App\Http\Requests;

class ApprovePinjamanRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id_pinjaman' => $this->route('id_pinjaman'),
        ]);
    }

    public function rules(): array
    {
        return [
            'id_pinjaman' => ['required', 'integer', 'exists:pinjamans,id_pinjaman'],
        ];
    }
}
