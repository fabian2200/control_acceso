<?php

namespace App\Console\Commands;

use App\Services\AccesoSyncService;
use Illuminate\Console\Command;

class SyncAcceso extends Command
{
    protected $signature = 'acceso:sync';

    protected $description = 'Sincroniza catálogo y marcas pendientes con la NUBE';

    public function handle(AccesoSyncService $sync): int
    {
        $resultado = $sync->ejecutar();

        if (! ($resultado['ok'] ?? false)) {
            $this->warn('Sync omitido: '.($resultado['error'] ?? 'error'));

            return self::SUCCESS;
        }

        $this->info('Sync ok. Pendientes: '.($resultado['pendientes'] ?? 0));

        return self::SUCCESS;
    }
}
