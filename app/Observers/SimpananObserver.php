<?php

namespace App\Observers;

use App\Models\Simpanan;
use App\Services\JurnalService;

class SimpananObserver
{
    public function created(Simpanan $simpanan): void
    {
        app(JurnalService::class)->catatSimpananMasuk($simpanan);
    }
}
