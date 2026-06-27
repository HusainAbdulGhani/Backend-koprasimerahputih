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
        return [
            'action' => $this->action,
            'id_pinjaman' => $this->pinjaman->id_pinjaman,
            'id_anggota' => $this->pinjaman->id_anggota,
            'status' => $this->pinjaman->status,
            'sent_at' => now()->toISOString(),
        ];
    }
}
