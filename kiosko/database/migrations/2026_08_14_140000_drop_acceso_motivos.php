<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acceso_salidas_ocasionales') && Schema::hasColumn('acceso_salidas_ocasionales', 'motivo_id')) {
            Schema::table('acceso_salidas_ocasionales', function (Blueprint $table) {
                $table->dropColumn('motivo_id');
            });
        }

        if (Schema::hasTable('acceso_registros') && Schema::hasColumn('acceso_registros', 'motivo_id')) {
            Schema::table('acceso_registros', function (Blueprint $table) {
                $table->dropColumn('motivo_id');
            });
        }

        Schema::dropIfExists('acceso_motivos');
    }

    public function down(): void
    {
        if (! Schema::hasTable('acceso_motivos')) {
            Schema::create('acceso_motivos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 80);
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('acceso_salidas_ocasionales') && ! Schema::hasColumn('acceso_salidas_ocasionales', 'motivo_id')) {
            Schema::table('acceso_salidas_ocasionales', function (Blueprint $table) {
                $table->unsignedBigInteger('motivo_id')->nullable()->after('registro_regreso_id');
            });
        }

        if (Schema::hasTable('acceso_registros') && ! Schema::hasColumn('acceso_registros', 'motivo_id')) {
            Schema::table('acceso_registros', function (Blueprint $table) {
                $table->unsignedBigInteger('motivo_id')->nullable()->after('minutos_tarde');
            });
        }
    }
};
