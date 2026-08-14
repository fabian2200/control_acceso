<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acceso_registros')) {
            return;
        }

        Schema::table('acceso_registros', function (Blueprint $table) {
            if (! Schema::hasColumn('acceso_registros', 'salida_ocasional_id')) {
                $table->unsignedBigInteger('salida_ocasional_id')->nullable()->after('terminal_id');
            }
            if (! Schema::hasColumn('acceso_registros', 'llego_tarde')) {
                $table->unsignedInteger('llego_tarde')->default(0)->after('hora_esperada');
            }
            if (! Schema::hasColumn('acceso_registros', 'llego_temprano')) {
                $table->unsignedInteger('llego_temprano')->default(0)->after('llego_tarde');
            }
            if (! Schema::hasColumn('acceso_registros', 'salio_temprano')) {
                $table->unsignedInteger('salio_temprano')->default(0)->after('llego_temprano');
            }
            if (! Schema::hasColumn('acceso_registros', 'salio_tarde')) {
                $table->unsignedInteger('salio_tarde')->default(0)->after('salio_temprano');
            }
        });

        if (Schema::hasColumn('acceso_registros', 'minutos_tarde')) {
            DB::statement('UPDATE acceso_registros SET llego_tarde = minutos_tarde WHERE tipo = ?', ['entrada']);
            DB::statement('UPDATE acceso_registros SET salio_tarde = minutos_tarde WHERE tipo = ?', ['salida']);
            Schema::table('acceso_registros', function (Blueprint $table) {
                $table->dropColumn('minutos_tarde');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('acceso_registros')) {
            return;
        }

        if (! Schema::hasColumn('acceso_registros', 'minutos_tarde')) {
            Schema::table('acceso_registros', function (Blueprint $table) {
                $table->unsignedInteger('minutos_tarde')->default(0)->after('hora_esperada');
            });
        }

        DB::statement('UPDATE acceso_registros SET minutos_tarde = GREATEST(llego_tarde, salio_tarde)');

        $drop = collect(['salida_ocasional_id', 'llego_tarde', 'llego_temprano', 'salio_temprano', 'salio_tarde'])
            ->filter(fn (string $col) => Schema::hasColumn('acceso_registros', $col))
            ->values()
            ->all();

        if ($drop !== []) {
            Schema::table('acceso_registros', function (Blueprint $table) use ($drop) {
                $table->dropColumn($drop);
            });
        }
    }
};
