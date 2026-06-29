<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $idAccount = (int) $this->route('id_account');
        $account = Account::with('anggota')->find($idAccount);
        $roles = $this->input('roles');
        if (! is_array($roles)) {
            $roles = $this->input('role') ? [$this->input('role')] : $account?->availableRoles() ?? [];
        }

        $rules = [
            'nama' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', 'in:Admin,Pengurus,Kasir,Gudang,Anggota'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['required', 'in:Admin,Pengurus,Kasir,Gudang,Anggota'],
            'id_cabang' => ['sometimes', 'integer', 'exists:cabangs,id_cabang'],
            'nip' => ['sometimes', 'string', 'max:50', Rule::unique('pengurus', 'nip')->ignore($idAccount, 'id_account')],
            'email' => ['sometimes', 'email', 'max:255'],
            'alamat' => ['sometimes', 'string'],
            'no_hp' => ['sometimes', 'string', 'max:15'],
            'status' => ['sometimes', 'in:Calon,Aktif,Non-Aktif,Tertunda,Tidak Aktif'],
        ];

        if (array_intersect($roles, ['Pengurus', 'Kasir', 'Gudang', 'Anggota'])) {
            $rules['id_cabang'] = ['required', 'integer', 'exists:cabangs,id_cabang'];
        }

        if (in_array('Anggota', $roles, true) && ! $account?->anggota) {
            $rules['email'] = ['required', 'email', 'max:255', 'unique:anggotas,email'];
            $rules['alamat'] = ['required', 'string'];
            $rules['no_hp'] = ['required', 'string', 'max:15'];
        }

        return $rules;
    }
}
