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
        Schema::create('direccion_envios', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->string('alias_direccion', 50)->nullable();
            $table->string('urbanizacion', 150)->nullable();
            $table->string('sector', 100)->nullable();
            $table->string('direccion', 255);
            $table->string('manzana', 20)->nullable();
            $table->string('lote', 20)->nullable();
            $table->string('referencia', 255)->nullable();
            $table->boolean('es_direccion_principal')->default(false);
            $table->integer('estado')->default(1)->comment('0=inactivo, 1=activo');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direccion_envios');
    }
};