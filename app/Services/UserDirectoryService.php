<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Admin;
use App\Models\Anggota;
use App\Models\Gudang;
use App\Models\Kasir;
use App\Models\Pengurus;
use App\Services\SimpananPolicyService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class UserDirectoryService
{
    private const STATUS_LABEL = [
        'Calon' => 'Tertunda',
        'Tertunda' => 'Tertunda',
        'Ditolak' => 'Ditolak',
        'Aktif' => 'Aktif',
        'Non-Aktif' => 'Tidak Aktif',
    ];

    /**
     * @return array{account: Account, profile: Pengurus|Kasir|Gudang}
     */
    public function createStaffUser(array $data): array
    {
        $role = $data['role'];

        if (! in_array($role, ['Pengurus', 'Kasir', 'Gudang'], true)) {
            throw new RuntimeException('Role staff tidak valid.');
        }

        return DB::transaction(function () use ($data, $role) {
            $account = Account::create([
                'username' => $data['username'],
                'password' => $data['password'],
                'role' => $role,
                'email' => $data['email'] ?? null,
            ]);

            $profile = match ($role) {
                'Pengurus' => Pengurus::create([
                    'id_account' => $account->id_account,
                    'nama_pengurus' => $data['nama'],
                    'nip' => $data['nip'] ?? ('PG-'.$account->id_account),
                    'id_cabang' => $data['id_cabang'],
                ]),
                'Kasir' => Kasir::create([
                    'id_account' => $account->id_account,
                    'nama_kasir' => $data['nama'],
                    'id_cabang' => $data['id_cabang'],
                ]),
                'Gudang' => Gudang::create([
                    'id_account' => $account->id_account,
                    'nama_petugas' => $data['nama'],
                    'id_cabang' => $data['id_cabang'],
                ]),
            };

            $this->syncAccountRoles($account, [$role]);

            return [
                'account' => $account,
                'profile' => $profile,
            ];
        });
    }

    /**
     * Buat member baru (semua role) untuk halaman Manajemen Anggota Admin.
     */
    public function createMember(array $data): array
    {
        $roles = array_values(array_unique($data['roles'] ?? [$data['role'] ?? 'Anggota']));
        $defaultRole = $data['role'] ?? (collect($roles)->first(fn ($role) => $role !== 'Anggota') ?: $roles[0]);

        return DB::transaction(function () use ($data, $roles, $defaultRole) {
            $account = Account::create([
                'username' => $data['username'],
                'password' => $data['password'],
                'role' => $defaultRole,
                'email' => $data['email'] ?? null,
            ]);
            $this->syncAccountRoles($account, $roles);

            $profile = null;
            if (in_array('Admin', $roles, true)) {
                $profile = Admin::create([
                    'id_account' => $account->id_account,
                    'nama_admin' => $data['nama'],
                ]);
            }
            if (in_array('Pengurus', $roles, true)) {
                $profile ??= Pengurus::create([
                    'id_account' => $account->id_account,
                    'nama_pengurus' => $data['nama'],
                    'nip' => $data['nip'] ?? ('PG-'.$account->id_account),
                    'id_cabang' => $data['id_cabang'],
                ]);
            }
            if (in_array('Kasir', $roles, true)) {
                $profile ??= Kasir::create([
                    'id_account' => $account->id_account,
                    'nama_kasir' => $data['nama'],
                    'id_cabang' => $data['id_cabang'],
                ]);
            }
            if (in_array('Gudang', $roles, true)) {
                $profile ??= Gudang::create([
                    'id_account' => $account->id_account,
                    'nama_petugas' => $data['nama'],
                    'id_cabang' => $data['id_cabang'],
                ]);
            }
            if (in_array('Anggota', $roles, true)) {
                $status = $data['status'] ?? 'Aktif';
                $anggota = Anggota::create([
                    'id_account' => $account->id_account,
                    'nama_anggota' => $data['nama'],
                    'alamat' => $data['alamat'],
                    'no_hp' => $data['no_hp'],
                    'email' => $data['email'],
                    'id_cabang' => $data['id_cabang'],
                    'tanggal_daftar' => now()->toDateString(),
                    'status' => $status,
                ]);
                if ($status === 'Aktif') {
                    $anggota->nomor_anggota = 'AGT-'.$anggota->id_cabang.'-'.str_pad((string) $anggota->id_anggota, 6, '0', STR_PAD_LEFT);
                    $anggota->save();
                    app(SimpananPolicyService::class)->ensureSimpananAwal($anggota);
                }
                $profile ??= $anggota;
            }

            return ['account' => $account, 'profile' => $profile];
        });
    }

    private function syncAccountRoles(Account $account, array $roles): void
    {
        $account->syncRoles($roles);
    }

    private function ensureMemberProfile(Account $account, string $name, int $idCabang, ?string $email = null): void
    {
        if ($account->anggota) {
            return;
        }

        $safeUsername = Str::slug($account->username, '-');
        $memberEmail = $email ?: "anggota-{$account->id_account}-{$safeUsername}@koperasi.local";
        if (Anggota::query()->where('email', $memberEmail)->exists()) {
            $memberEmail = "anggota-{$account->id_account}-{$safeUsername}@koperasi.local";
        }
        $member = Anggota::create([
            'id_account' => $account->id_account,
            'nomor_anggota' => null,
            'nama_anggota' => $name,
            'alamat' => '-',
            'no_hp' => '-',
            'email' => $memberEmail,
            'tanggal_daftar' => now()->toDateString(),
            'status' => 'Aktif',
            'id_cabang' => $idCabang,
        ]);
        $member->nomor_anggota = 'AGT-'.$member->id_cabang.'-'.str_pad((string) $member->id_anggota, 6, '0', STR_PAD_LEFT);
        $member->save();
        app(SimpananPolicyService::class)->ensureSimpananAwal($member);
    }

    /**
     * Daftar unified untuk UI Manajemen Anggota (Admin only).
     */
    public function listMembersForAdmin(array $filters = [], int $perPage = 9): LengthAwarePaginator
    {
        $members = $this->collectAllMembers();

        if (! empty($filters['q'])) {
            $term = mb_strtolower((string) $filters['q']);
            $members = $members->filter(function ($m) use ($term) {
                return str_contains(mb_strtolower($m['nama']), $term)
                    || str_contains(mb_strtolower($m['email'] ?? ''), $term)
                    || str_contains(mb_strtolower($m['telepon'] ?? ''), $term)
                    || str_contains(mb_strtolower($m['peran']), $term)
                    || str_contains(mb_strtolower($m['username'] ?? ''), $term);
            });
        }

        if (! empty($filters['role'])) {
            $members = $members->where('peran', $filters['role']);
        }

        if (! empty($filters['status'])) {
            $members = $members->where('status_label', $this->normalizeStatusFilter($filters['status']));
        }

        $members = $members->sort(function ($a, $b) {
            $aPending = in_array($a['status'], ['Tertunda', 'Calon'], true);
            $bPending = in_array($b['status'], ['Tertunda', 'Calon'], true);

            if ($aPending && !$bPending) {
                return -1;
            }
            if (!$aPending && $bPending) {
                return 1;
            }

            return strcasecmp($a['nama'], $b['nama']);
        })->values();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = $members->count();
        $items = $members->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function updateMember(int $idAccount, array $data): array
    {
        $account = Account::with(['admin', 'pengurus', 'kasir', 'gudang', 'anggota'])->find($idAccount);
        if (! $account) {
            throw new RuntimeException('Akun tidak ditemukan.');
        }

        return DB::transaction(function () use ($account, $data) {
            $roles = array_values(array_unique($data['roles'] ?? $account->availableRoles()));
            if (empty($roles)) {
                throw new RuntimeException('Minimal satu role harus dipilih.');
            }
            if ($account->anggota && ! in_array('Anggota', $roles, true)) {
                throw new RuntimeException('Role Anggota tidak bisa dicabut dari akun yang sudah memiliki profil anggota. Nonaktifkan status anggota jika keanggotaan berhenti.');
            }
            $defaultRole = $data['role'] ?? (in_array($account->role, $roles, true)
                ? $account->role
                : (collect($roles)->first(fn ($role) => $role !== 'Anggota') ?: $roles[0]));

            $this->ensureProfilesForRoles($account, $roles, $data);
            $account->role = $defaultRole;
            $this->syncAccountRoles($account, $roles);

            if (! empty($data['password'])) {
                $account->password = $data['password'];
            }
            if (! empty($data['email'])) {
                $account->email = $data['email'];
            }
            $account->save();

            $profile = match ($account->role) {
                'Admin' => $this->updateAdminProfile($account, $data),
                'Pengurus' => $this->updatePengurusProfile($account, $data),
                'Kasir' => $this->updateKasirProfile($account, $data),
                'Gudang' => $this->updateGudangProfile($account, $data),
                'Anggota' => $this->updateAnggotaProfile($account, $data),
                default => throw new RuntimeException('Role tidak dikenali.'),
            };

            return [
                'account' => $account->fresh(),
                'profile' => $profile,
                'member' => $this->mapAccountToMember($account->fresh()->load([
                    'admin', 'pengurus.cabang', 'kasir.cabang', 'gudang.cabang', 'anggota.cabang',
                ])),
            ];
        });
    }

    public function deleteMember(int $idAccount): void
    {
        $account = Account::with([
            'admin', 'pengurus', 'kasir', 'gudang', 'anggota',
        ])->find($idAccount);

        if (! $account) {
            throw new RuntimeException('Akun tidak ditemukan.');
        }

        DB::transaction(function () use ($account) {
            $account->admin?->delete();
            $account->pengurus?->delete();
            $account->kasir?->delete();
            $account->gudang?->delete();
            $account->anggota?->delete();
            $account->delete();
        });
    }

    private function ensureProfilesForRoles(Account $account, array $roles, array $data): void
    {
        $name = $data['nama'] ?? $this->profileName($account);

        if (in_array('Admin', $roles, true) && ! $account->admin) {
            $account->admin()->create(['nama_admin' => $name]);
        }
        if (in_array('Pengurus', $roles, true) && ! $account->pengurus) {
            $account->pengurus()->create([
                'nama_pengurus' => $name,
                'nip' => $data['nip'] ?? ('PG-'.$account->id_account),
                'id_cabang' => $data['id_cabang'],
            ]);
        }
        if (in_array('Kasir', $roles, true) && ! $account->kasir) {
            $account->kasir()->create([
                'nama_kasir' => $name,
                'id_cabang' => $data['id_cabang'],
            ]);
        }
        if (in_array('Gudang', $roles, true) && ! $account->gudang) {
            $account->gudang()->create([
                'nama_petugas' => $name,
                'id_cabang' => $data['id_cabang'],
            ]);
        }
        if (in_array('Anggota', $roles, true) && ! $account->anggota) {
            $member = $account->anggota()->create([
                'nomor_anggota' => null,
                'nama_anggota' => $name,
                'alamat' => $data['alamat'],
                'no_hp' => $data['no_hp'],
                'email' => $data['email'] ?? $account->email,
                'tanggal_daftar' => now()->toDateString(),
                'status' => $this->normalizeStatusInput($data['status'] ?? 'Aktif'),
                'id_cabang' => $data['id_cabang'],
            ]);
            if ($member->status === 'Aktif') {
                $member->nomor_anggota = 'AGT-'.$member->id_cabang.'-'.str_pad((string) $member->id_anggota, 6, '0', STR_PAD_LEFT);
                $member->save();
                app(SimpananPolicyService::class)->ensureSimpananAwal($member);
            }
        }

        $account->load(['admin', 'pengurus', 'kasir', 'gudang', 'anggota']);
    }

    private function profileName(Account $account): string
    {
        return $account->admin?->nama_admin
            ?? $account->pengurus?->nama_pengurus
            ?? $account->kasir?->nama_kasir
            ?? $account->gudang?->nama_petugas
            ?? $account->anggota?->nama_anggota
            ?? $account->username;
    }

    public function mapAccountToMember(Account $account): array
    {
        $roles = $account->availableRoles();

        if (in_array('Admin', $roles, true) && $account->admin) {
            return $this->formatMember(
                idAccount: $account->id_account,
                idProfile: $account->admin->id_admin,
                nama: $account->admin->nama_admin,
                email: $account->email ?? ($account->username.'@koperasi.id'),
                username: $account->username,
                peran: 'Admin',
                status: 'Aktif',
                telepon: '-',
                roles: $roles,
            );
        }

        if (in_array('Pengurus', $roles, true) && $account->pengurus) {
            $p = $account->pengurus;

            return $this->formatMember(
                idAccount: $account->id_account,
                idProfile: $p->id_pengurus,
                nama: $p->nama_pengurus,
                email: $account->email ?? ($account->username.'@koperasi.id'),
                username: $account->username,
                peran: 'Pengurus',
                status: 'Aktif',
                telepon: '-',
                idCabang: $p->id_cabang,
                namaCabang: $p->cabang?->nama_cabang,
                roles: $roles,
            );
        }

        if (in_array('Kasir', $roles, true) && $account->kasir) {
            $k = $account->kasir;

            return $this->formatMember(
                idAccount: $account->id_account,
                idProfile: $k->id_kasir,
                nama: $k->nama_kasir,
                email: $account->email ?? ($account->username.'@koperasi.id'),
                username: $account->username,
                peran: 'Kasir',
                status: 'Aktif',
                telepon: '-',
                idCabang: $k->id_cabang,
                namaCabang: $k->cabang?->nama_cabang,
                roles: $roles,
            );
        }

        if (in_array('Gudang', $roles, true) && $account->gudang) {
            $g = $account->gudang;

            return $this->formatMember(
                idAccount: $account->id_account,
                idProfile: $g->id_gudang,
                nama: $g->nama_petugas,
                email: $account->email ?? ($account->username.'@koperasi.id'),
                username: $account->username,
                peran: 'Gudang',
                status: 'Aktif',
                telepon: '-',
                idCabang: $g->id_cabang,
                namaCabang: $g->cabang?->nama_cabang,
                roles: $roles,
            );
        }

        if (in_array('Anggota', $roles, true) && $account->anggota) {
            $a = $account->anggota;

            return $this->formatMember(
                idAccount: $account->id_account,
                idProfile: $a->id_anggota,
                nama: $a->nama_anggota,
                email: $a->email,
                username: $account->username,
                peran: 'Anggota',
                status: $a->status,
                telepon: $a->no_hp,
                idCabang: $a->id_cabang,
                namaCabang: $a->cabang?->nama_cabang,
                alamat: $a->alamat,
                roles: $roles,
            );
        }

        return $this->formatMember(
            idAccount: $account->id_account,
            idProfile: null,
            nama: $account->username,
            email: $account->email ?? ($account->username.'@koperasi.id'),
            username: $account->username,
            peran: $account->role,
            status: 'Aktif',
            telepon: '-',
            roles: $roles,
        );
    }

    private function collectAllMembers(): Collection
    {
        return Account::with([
            'admin', 'pengurus.cabang', 'kasir.cabang', 'gudang.cabang', 'anggota.cabang',
        ])->get()->map(fn (Account $acc) => $this->mapAccountToMember($acc));
    }

    private function formatMember(
        int $idAccount,
        ?int $idProfile,
        string $nama,
        string $email,
        string $username,
        string $peran,
        string $status,
        string $telepon,
        ?int $idCabang = null,
        ?string $namaCabang = null,
        ?string $alamat = null,
        array $roles = [],
    ): array {
        $words = preg_split('/\s+/', trim($nama)) ?: [];
        $inicial = collect($words)->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');

        $statusLabel = self::STATUS_LABEL[$status] ?? $status;

        return [
            'id_account' => $idAccount,
            'id_profile' => $idProfile,
            'nama' => $nama,
            'email' => $email,
            'username' => $username,
            'peran' => $peran,
            'status' => $status,
            'status_label' => $statusLabel,
            'telepon' => $telepon,
            'id_cabang' => $idCabang,
            'nama_cabang' => $namaCabang,
            'alamat' => $alamat,
            'roles' => $roles,
            'inicial' => $inicial ?: mb_strtoupper(mb_substr($nama, 0, 2)),
        ];
    }

    private function normalizeStatusFilter(string $status): string
    {
        return match ($status) {
            'Tertunda', 'Calon' => 'Tertunda',
            'Ditolak' => 'Ditolak',
            'Tidak Aktif', 'Non-Aktif' => 'Tidak Aktif',
            default => 'Aktif',
        };
    }

    private function normalizeStatusInput(string $status): string
    {
        return match ($status) {
            'Tertunda' => 'Tertunda',
            'Ditolak' => 'Ditolak',
            'Tidak Aktif' => 'Non-Aktif',
            default => $status,
        };
    }

    private function updateAdminProfile(Account $account, array $data): Admin
    {
        $admin = $account->admin ?? Admin::firstOrCreate(['id_account' => $account->id_account], ['nama_admin' => $data['nama'] ?? $account->username]);
        if (! empty($data['nama'])) {
            $admin->nama_admin = $data['nama'];
            $admin->save();
        }

        return $admin;
    }

    private function updatePengurusProfile(Account $account, array $data): Pengurus
    {
        $p = $account->pengurus ?? throw new RuntimeException('Profil pengurus tidak ditemukan.');
        if (! empty($data['nama'])) {
            $p->nama_pengurus = $data['nama'];
        }
        if (! empty($data['nip'])) {
            $p->nip = $data['nip'];
        }
        if (! empty($data['id_cabang'])) {
            $p->id_cabang = $data['id_cabang'];
        }
        $p->save();

        return $p->fresh('cabang');
    }

    private function updateKasirProfile(Account $account, array $data): Kasir
    {
        $k = $account->kasir ?? throw new RuntimeException('Profil kasir tidak ditemukan.');
        if (! empty($data['nama'])) {
            $k->nama_kasir = $data['nama'];
        }
        if (! empty($data['id_cabang'])) {
            $k->id_cabang = $data['id_cabang'];
        }
        $k->save();

        return $k->fresh('cabang');
    }

    private function updateGudangProfile(Account $account, array $data): Gudang
    {
        $g = $account->gudang ?? throw new RuntimeException('Profil gudang tidak ditemukan.');
        if (! empty($data['nama'])) {
            $g->nama_petugas = $data['nama'];
        }
        if (! empty($data['id_cabang'])) {
            $g->id_cabang = $data['id_cabang'];
        }
        $g->save();

        return $g->fresh('cabang');
    }

    private function updateAnggotaProfile(Account $account, array $data): Anggota
    {
        $a = $account->anggota ?? throw new RuntimeException('Profil anggota tidak ditemukan.');
        if (! empty($data['nama'])) {
            $a->nama_anggota = $data['nama'];
        }
        if (! empty($data['email'])) {
            $a->email = $data['email'];
        }
        if (! empty($data['alamat'])) {
            $a->alamat = $data['alamat'];
        }
        if (! empty($data['no_hp'])) {
            $a->no_hp = $data['no_hp'];
        }
        if (! empty($data['id_cabang'])) {
            $a->id_cabang = $data['id_cabang'];
        }
        if (! empty($data['status'])) {
            $a->status = $this->normalizeStatusInput($data['status']);
            if ($a->status === 'Aktif' && ! $a->nomor_anggota) {
                $a->nomor_anggota = 'AGT-'.$a->id_cabang.'-'.str_pad((string) $a->id_anggota, 6, '0', STR_PAD_LEFT);
            }
        }
        $a->save();

        if ($a->status === 'Aktif') {
            app(SimpananPolicyService::class)->ensureSimpananAwal($a);
        }

        return $a->fresh('cabang');
    }

    public function listStaff(?string $role = null, ?int $idCabang = null): array
    {
        $result = [];

        if (! $role || $role === 'Pengurus') {
            $q = Pengurus::with(['account', 'cabang']);
            if ($idCabang) {
                $q->where('id_cabang', $idCabang);
            }
            $result['pengurus'] = $q->get();
        }

        if (! $role || $role === 'Kasir') {
            $q = Kasir::with(['account', 'cabang']);
            if ($idCabang) {
                $q->where('id_cabang', $idCabang);
            }
            $result['kasir'] = $q->get();
        }

        if (! $role || $role === 'Gudang') {
            $q = Gudang::with(['account', 'cabang']);
            if ($idCabang) {
                $q->where('id_cabang', $idCabang);
            }
            $result['gudang'] = $q->get();
        }

        if (! $role || $role === 'Admin') {
            $result['admin'] = Admin::with('account')->get();
        }

        return $result;
    }
}
