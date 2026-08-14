<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acceso_registros')) {
            if (Schema::hasColumn('acceso_registros', 'tipo')) {
                DB::table('acceso_registros')->whereIn('tipo', ['salida_ocasional', 'regreso'])->delete();
            }

            $drop = collect(['motivo_texto', 'permiso_id', 'hora_regreso_esperada', 'salida_ocasional_id'])
                ->filter(fn (string $col) => Schema::hasColumn('acceso_registros', $col))
                ->values()
                ->all();

            if ($drop !== []) {
                Schema::table('acceso_registros', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }

            if (! Schema::hasColumn('acceso_registros', 'hora_esperada')) {
                Schema::table('acceso_registros', function (Blueprint $table) {
                    $table->time('hora_esperada')->nullable()->after('foto');
                });
            }

            DB::statement("ALTER TABLE acceso_registros MODIFY tipo ENUM('entrada','salida') NOT NULL");
        }

        if (Schema::hasTable('acceso_salidas_ocasionales')) {
            $drop = collect(['registro_salida_id', 'registro_regreso_id'])
                ->filter(fn (string $col) => Schema::hasColumn('acceso_salidas_ocasionales', $col))
                ->values()
                ->all();

            if ($drop !== []) {
                Schema::table('acceso_salidas_ocasionales', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }

            Schema::table('acceso_salidas_ocasionales', function (Blueprint $table) {
                if (! Schema::hasColumn('acceso_salidas_ocasionales', 'terminal_id')) {
                    $table->unsignedBigInteger('terminal_id')->nullable()->after('empleado_id');
                }
                if (! Schema::hasColumn('acceso_salidas_ocasionales', 'minutos_tarde')) {
                    $table->unsignedInteger('minutos_tarde')->default(0);
                }
                if (! Schema::hasColumn('acceso_salidas_ocasionales', 'foto_salida')) {
                    $table->string('foto_salida', 255)->nullable();
                }
                if (! Schema::hasColumn('acceso_salidas_ocasionales', 'foto_regreso')) {
                    $table->string('foto_regreso', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};
