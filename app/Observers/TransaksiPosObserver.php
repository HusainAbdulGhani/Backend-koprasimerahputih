<?php

namespace App\Observers;

use App\Models\TransaksiPos;
use App\Services\JurnalService;

class TransaksiPosObserver
{
    public function created(TransaksiPos $transaksiPos): void
    {
        app(JurnalService::class)->catatTransaksiPos($transaksiPos);
    }
}
