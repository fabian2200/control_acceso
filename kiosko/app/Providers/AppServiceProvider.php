<?php

namespace App\Providers;

use App\Services\AccesoSyncService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Carbon\Carbon::setLocale(config('app.locale'));

        View::composer('layouts.kiosko', function ($view) {
            try {
                $ui = app(AccesoSyncService::class)->estadoUi();
            } catch (Throwable) {
                $ui = [
                    'pendientes' => 0,
                    'en_linea' => false,
                    'etiqueta_red' => 'Kiosko local',
                    'etiqueta_sync' => 'Local',
                ];
            }

            $view->with('syncUi', $ui);
        });
    }
}
