<?php

namespace App\Http\Controllers;

use App\Events\AccountUpdated;
use App\Models\Account;
use App\Models\Anggota;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountSettingsController extends Controller
{
    use ApiResponse;

    public function updateProfile(Request $request): JsonResponse
    {
        $account = $request->user()->load(['admin', 'pengurus', 'kasir', 'gudang', 'anggota']);
        $role = $account->resolveActiveRole($request->header('X-Active-Role'));

        $emailRules = [
            'nullable',
            'email',
            'max:255',
            Rule::unique('accounts', 'email')->ignore($account->id_account, 'id_account'),
        ];
        if ($role === 'Anggota') {
            $emailRules[] = Rule::unique('anggotas', 'email')->ignore($account->anggota?->id_anggota, 'id_anggota');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'profile_photo' => ['nullable', 'string'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan. Gunakan email lain.',
            'phone.max' => 'Nomor telepon terlalu panjang.',
            'address.max' => 'Alamat terlalu panjang.',
        ]);

        $account->email = $validated['email'] ?? null;
        if (array_key_exists('profile_photo', $validated)) {
            $account->profile_photo = $validated['profile_photo'];
        }
        $account->save();

        match ($role) {
            'Admin' => $account->admin()->updateOrCreate(
                ['id_account' => $account->id_account],
                ['nama_admin' => $validated['name']]
            ),
            'Pengurus' => $account->pengurus?->update(['nama_pengurus' => $validated['name']]),
            'Kasir' => $account->kasir?->update(['nama_kasir' => $validated['name']]),
            'Gudang' => $account->gudang?->update(['nama_petugas' => $validated['name']]),
            'Anggota' => $this->updateAnggotaProfile($account, $validated),
            default => null,
        };

        $fresh = $account->fresh(['roles', 'admin', 'pengurus.cabang', 'kasir.cabang', 'gudang.cabang', 'anggota.cabang']);
        broadcast(new AccountUpdated('profile-updated', $fresh));

        return $this->successResponse('Profil berhasil diperbarui.', $fresh);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $account = $request->user();
        if (! Hash::check($validated['current_password'], $account->password)) {
            return $this->errorResponse('Password saat ini salah.', null, 422);
        }

        $account->password = $validated['password'];
        $account->save();

        return $this->successResponse('Password berhasil diperbarui.', null);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
        ], [
            'identifier.required' => 'Username atau email wajib diisi.',
        ]);

        $account = $this->findAccountByIdentifier($validated['identifier']);
        if (! $account) {
            return $this->errorResponse('Akun tidak ditemukan. Periksa username atau email.', null, 404);
        }

        $code = (string) random_int(100000, 999999);
        Cache::put("password-reset:{$account->id_account}", $code, now()->addMinutes(15));

        return $this->successResponse('Kode reset simulasi berhasil dibuat.', [
            'username' => $account->username,
            'email' => $account->email,
            'reset_code' => $code,
            'expires_in_minutes' => 15,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
            'reset_code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'identifier.required' => 'Username atau email wajib diisi.',
            'reset_code.required' => 'Kode reset wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $account = $this->findAccountByIdentifier($validated['identifier']);
        if (! $account) {
            return $this->errorResponse('Akun tidak ditemukan. Periksa username atau email.', null, 404);
        }

        $cacheKey = "password-reset:{$account->id_account}";
        if (Cache::get($cacheKey) !== $validated['reset_code']) {
            return $this->errorResponse('Kode reset salah atau sudah kedaluwarsa.', null, 422);
        }

        $account->password = $validated['password'];
        $account->save();
        Cache::forget($cacheKey);

        return $this->successResponse('Password berhasil direset. Silakan login dengan password baru.', null);
    }

    private function updateAnggotaProfile(Account $account, array $data): void
    {
        $anggota = $account->anggota;
        if (! $anggota) {
            return;
        }

        $anggota->nama_anggota = $data['name'];
        $anggota->email = $data['email'] ?? $anggota->email;
        $anggota->no_hp = $data['phone'] ?? $anggota->no_hp;
        $anggota->alamat = $data['address'] ?? $anggota->alamat;
        $anggota->save();
    }

    private function findAccountByIdentifier(string $identifier): ?Account
    {
        $account = Account::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if ($account) {
            return $account;
        }

        $anggota = Anggota::query()->where('email', $identifier)->first();
        return $anggota ? Account::find($anggota->id_account) : null;
    }
}
