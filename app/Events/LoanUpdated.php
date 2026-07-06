<?php

namespace App\Events;

use App\Models\Pinjaman;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public Pinjaman $pinjaman
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('loans');
    }

    public function broadcastAs(): string
    {
        return 'loan.updated';
    }

    public function broadcastWith(): array
    {
        if (!$this->pinjaman->relationLoaded('anggota')) {
            $this->pinjaman->load('anggota');
        }
        return [
            'action' => $this->action,
            'id_pinjaman' => $this->pinjaman->id_pinjaman,
            'id_anggota' => $this->pinjaman->id_anggota,
            'nama_anggota' => $this->pinjaman->anggota?->nama_anggota,
            'id_cabang' => $this->pinjaman->anggota?->id_cabang,
            'jumlah' => $this->pinjaman->jumlah_pinjaman,
            'status' => $this->pinjaman->status,
            'sent_at' => now()->toISOString(),
        ];
    }
}
