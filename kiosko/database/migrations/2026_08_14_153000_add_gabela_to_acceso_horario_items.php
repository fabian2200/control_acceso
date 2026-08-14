<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acceso_horario_items')) {
            return;
        }

        Schema::table('acceso_horario_items', function (Blueprint $table) {
            if (! Schema::hasColumn('acceso_horario_items', 'gabela_entrada_manana')) {
                $table->unsignedSmallInteger('gabela_entrada_manana')->nullable()->after('entrada_manana');
            }
            if (! Schema::hasColumn('acceso_horario_items', 'gabela_salida_manana')) {
                $table->unsignedSmallInteger('gabela_salida_manana')->nullable()->after('salida_manana');
            }
            if (! Schema::hasColumn('acceso_horario_items', 'gabela_entrada_tarde')) {
                $table->unsignedSmallInteger('gabela_entrada_tarde')->nullable()->after('entrada_tarde');
            }
            if (! Schema::hasColumn('acceso_horario_items', 'gabela_salida_tarde')) {
                $table->unsignedSmallInteger('gabela_salida_tarde')->nullable()->after('salida_tarde');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('acceso_horario_items')) {
            return;
        }

        Schema::table('acceso_horario_items', function (Blueprint $table) {
            foreach (['gabela_entrada_manana', 'gabela_salida_manana', 'gabela_entrada_tarde', 'gabela_salida_tarde'] as $col) {
                if (Schema::hasColumn('acceso_horario_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
