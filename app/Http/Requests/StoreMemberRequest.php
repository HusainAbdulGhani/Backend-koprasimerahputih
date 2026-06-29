<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreMemberRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roles = $this->input('roles', $this->input('role') ? [$this->input('role')] : []);

        $rules = [
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:accounts,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'required_without:roles', 'in:Admin,Pengurus,Kasir,Gudang,Anggota'],
            'roles' => ['nullable', 'required_without:role', 'array', 'min:1'],
            'roles.*' => ['required', 'in:Admin,Pengurus,Kasir,Gudang,Anggota'],
            'nama' => ['required', 'string', 'max:255'],
        ];

        if (array_intersect($roles, ['Pengurus', 'Kasir', 'Gudang', 'Anggota'])) {
            $rules['id_cabang'] = ['required', 'integer', 'exists:cabangs,id_cabang'];
        }

        if (in_array('Pengurus', $roles, true)) {
            $rules['nip'] = ['nullable', 'string', 'max:50', 'unique:pengurus,nip'];
        }

        if (in_array('Anggota', $roles, true)) {
            $rules['email'] = ['required', 'email', 'max:255', 'unique:anggotas,email'];
            $rules['alamat'] = ['required', 'string'];
            $rules['no_hp'] = ['required', 'string', 'max:15'];
            $rules['status'] = ['nullable', 'in:Calon,Aktif,Non-Aktif'];
        }

        return $rules;
    }
}
