<?php

namespace App\Http\Requests;

class RegisterAnggotaRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Bersihkan data jika username sama dengan anggota yang ditolak
        $username = $this->input('username');
        if ($username) {
            $existingAccount = \App\Models\Account::where('username', $username)->first();
            if ($existingAccount && $existingAccount->anggota && $existingAccount->anggota->status === 'Ditolak') {
                $existingAccount->anggota->delete();
                $existingAccount->delete();
            }
        }

        // Bersihkan data jika email sama dengan anggota yang ditolak
        $email = $this->input('email');
        if ($email) {
            $existingMember = \App\Models\Anggota::where('email', $email)->first();
            if ($existingMember && $existingMember->status === 'Ditolak') {
                $account = $existingMember->account;
                $existingMember->delete();
                if ($account) {
                    $account->delete();
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:accounts,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'nama_anggota' => ['required', 'string', 'max:255'],
            'alamat' => ['required', 'string'],
            'no_hp' => ['required', 'string', 'max:15'],
            'email' => ['required', 'email', 'max:255', 'unique:anggotas,email'],
            'id_cabang' => ['required', 'integer', 'exists:cabangs,id_cabang'],
        ];
    }
}

