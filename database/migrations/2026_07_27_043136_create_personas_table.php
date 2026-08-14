<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tipo_documento_identidad_id')->nullable();
            $table->foreign('tipo_documento_identidad_id')->references('id')->on('tipo_documento_identidades');
            $table->string('numero_documento', 20)->unique()->comment('DNI, RUC, etc.');
            $table->string('nombres', 150)->comment('Nombres o Razón Social del proveedor');
            $table->string('apellido_paterno', 100)->nullable();
            $table->string('apellido_materno', 100)->nullable();
            $table->string('numero_celular', 20)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->unsignedBigInteger('departamento_id')->nullable();
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->unsignedBigInteger('distrito_id')->nullable();
            $table->foreign('departamento_id')->references('id')->on('departamentos');
            $table->foreign('provincia_id')->references('id')->on('provincias');
            $table->foreign('distrito_id')->references('id')->on('distritos');
            $table->integer('estado')->default(1)->comment('0=inactivo, 1=activo');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
