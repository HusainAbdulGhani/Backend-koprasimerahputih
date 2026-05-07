<?php

namespace App\Http\Requests;

class StorePinjamanRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_anggota' => ['required', 'integer', 'exists:anggotas,id_anggota'],
            'id_pengurus_acc' => ['required', 'integer', 'exists:pengurus,id_pengurus'],
            'jumlah_pinjaman' => ['required', 'numeric', 'min:1'],
            'tenor' => ['required', 'in:6,12,18,24'],
            'tanggal_pengajuan' => ['required', 'date'],
            'status' => ['nullable', 'in:Pending,Approved,Rejected'],
        ];
    }
}
