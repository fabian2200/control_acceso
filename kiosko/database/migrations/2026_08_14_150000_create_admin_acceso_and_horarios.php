<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_acceso', function (Blueprint $table) {
            $table->id();
            $table->string('usuario', 80)->unique();
            $table->string('password');
        });

        Schema::create('acceso_horarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('acceso_horario_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id');
            $table->unsignedTinyInteger('dia_semana');
            $table->time('entrada_manana')->nullable();
            $table->unsignedSmallInteger('gabela_entrada_manana')->nullable();
            $table->time('salida_manana')->nullable();
            $table->unsignedSmallInteger('gabela_salida_manana')->nullable();
            $table->time('entrada_tarde')->nullable();
            $table->unsignedSmallInteger('gabela_entrada_tarde')->nullable();
            $table->time('salida_tarde')->nullable();
            $table->unsignedSmallInteger('gabela_salida_tarde')->nullable();
            $table->timestamps();

            $table->unique(['horario_id', 'dia_semana'], 'acceso_horario_items_dia');
            $table->foreign('horario_id')
                ->references('id')
                ->on('acceso_horarios')
                ->cascadeOnDelete();
        });

        Schema::create('acceso_empleado_horarios', function (Blueprint $table) {
            $table->id();
            $table->integer('empleado_id');
            $table->unsignedBigInteger('horario_id');
            $table->timestamps();

            $table->unique('empleado_id', 'acceso_empleado_horario_unico');
            $table->foreign('horario_id')
                ->references('id')
                ->on('acceso_horarios')
                ->restrictOnDelete();
        });

        if (DB::table('admin_acceso')->count() === 0) {
            DB::table('admin_acceso')->insert([
                'usuario' => 'admin',
                'password' => Hash::make('1234'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acceso_empleado_horarios');
        Schema::dropIfExists('acceso_horario_items');
        Schema::dropIfExists('acceso_horarios');
        Schema::dropIfExists('admin_acceso');
    }
};
