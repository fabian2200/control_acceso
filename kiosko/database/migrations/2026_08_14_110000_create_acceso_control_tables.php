<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acceso_terminales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 120);
            $table->string('ubicacion', 200)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('acceso_salidas_ocasionales', function (Blueprint $table) {
            $table->id();
            $table->integer('empleado_id');
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->string('motivo_texto', 120)->nullable();
            $table->unsignedInteger('permiso_id')->nullable();
            $table->dateTime('salida_en');
            $table->time('hora_regreso_esperada');
            $table->dateTime('regreso_en')->nullable();
            $table->unsignedInteger('minutos_tarde')->default(0);
            $table->string('foto_salida', 255)->nullable();
            $table->string('foto_regreso', 255)->nullable();
            $table->enum('estado', ['abierta', 'cerrada', 'vencida'])->default('abierta');
            $table->boolean('revisada_rrhh')->default(false);
            $table->timestamps();

            $table->index(['empleado_id', 'estado'], 'acceso_salidas_empleado_estado');
        });

        Schema::create('acceso_registros', function (Blueprint $table) {
            $table->id();
            $table->integer('empleado_id');
            $table->unsignedBigInteger('terminal_id')->nullable();
            $table->unsignedBigInteger('salida_ocasional_id')->nullable();
            $table->enum('tipo', ['entrada', 'salida']);
            $table->date('fecha');
            $table->time('hora');
            $table->dateTime('registrado_en');
            $table->string('foto', 255)->nullable();
            $table->time('hora_esperada')->nullable();
            $table->unsignedInteger('llego_tarde')->default(0);
            $table->unsignedInteger('llego_temprano')->default(0);
            $table->unsignedInteger('salio_temprano')->default(0);
            $table->unsignedInteger('salio_tarde')->default(0);
            $table->boolean('sincronizado')->default(true);
            $table->timestamps();

            $table->index(['empleado_id', 'fecha'], 'acceso_registros_empleado_fecha');
            $table->index('tipo');
        });

        DB::table('acceso_terminales')->insert([
            'codigo' => 'REC-01',
            'nombre' => 'Recepción',
            'ubicacion' => 'Torre Norte',
            'activo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }

    public function down(): void
    {
        Schema::dropIfExists('acceso_registros');
        Schema::dropIfExists('acceso_salidas_ocasionales');
        Schema::dropIfExists('acceso_terminales');
    }
};
