<?php

namespace App\Events;

use App\Models\Account;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public Account $account,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('accounts');
    }

    public function broadcastAs(): string
    {
        return 'account.updated';
    }

    public function broadcastWith(): array
    {
        if (!$this->account->relationLoaded('anggota') || ($this->account->anggota && !$this->account->anggota->relationLoaded('cabang'))) {
            $this->account->load('anggota.cabang');
        }
        return [
            'action' => $this->action,
            'id_account' => $this->account->id_account,
            'id_anggota' => $this->account->anggota?->id_anggota,
            'nama_anggota' => $this->account->anggota?->nama_anggota,
            'id_cabang' => $this->account->anggota?->id_cabang,
            'nama_cabang' => $this->account->anggota?->cabang?->nama_cabang,
            'available_roles' => $this->account->availableRoles(),
            'anggota_status' => $this->account->anggota?->status,
            'sent_at' => now()->toISOString(),
        ];
    }
}
