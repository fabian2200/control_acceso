<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acceso_salidas_ocasionales') && ! Schema::hasColumn('acceso_salidas_ocasionales', 'permiso_id')) {
            Schema::table('acceso_salidas_ocasionales', function (Blueprint $table) {
                $table->unsignedInteger('permiso_id')->nullable()->after('motivo_texto');
            });
        }

        if (Schema::hasTable('acceso_registros') && ! Schema::hasColumn('acceso_registros', 'permiso_id')) {
            Schema::table('acceso_registros', function (Blueprint $table) {
                $table->unsignedInteger('permiso_id')->nullable()->after('motivo_texto');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('acceso_salidas_ocasionales') && Schema::hasColumn('acceso_salidas_ocasionales', 'permiso_id')) {
            Schema::table('acceso_salidas_ocasionales', function (Blueprint $table) {
                $table->dropColumn('permiso_id');
            });
        }

        if (Schema::hasTable('acceso_registros') && Schema::hasColumn('acceso_registros', 'permiso_id')) {
            Schema::table('acceso_registros', function (Blueprint $table) {
                $table->dropColumn('permiso_id');
            });
        }
    }
};
