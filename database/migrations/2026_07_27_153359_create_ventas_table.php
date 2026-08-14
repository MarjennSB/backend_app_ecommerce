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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->unsignedBigInteger('direccion_envio_id')->nullable();
            $table->foreign('direccion_envio_id')->references('id')->on('direccion_envios');
            $table->unsignedBigInteger('tipo_metodo_pago_id')->nullable();
            $table->foreign('tipo_metodo_pago_id')->references('id')->on('tipo_metodo_pagos');
            $table->string('codigo_transaccion_pasarela', 150)->nullable();
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('descuento_total', 10, 2)->default(0.00);
            $table->decimal('costo_envio', 10, 2)->default(0.00);
            $table->decimal('impuestos_igv', 10, 2)->nullable();
            $table->decimal('monto_total', 10, 2)->nullable();
            $table->string('estado_venta', 30)->default('PENDIENTE');
            $table->date('fecha_venta')->nullable();
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
        Schema::dropIfExists('ventas');
    }
};
