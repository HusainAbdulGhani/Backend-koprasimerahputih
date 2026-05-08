<?php

namespace App\Providers;

use App\Models\Simpanan;
use App\Models\TransaksiPos;
use App\Observers\SimpananObserver;
use App\Observers\TransaksiPosObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Simpanan::observe(SimpananObserver::class);
    }
}
