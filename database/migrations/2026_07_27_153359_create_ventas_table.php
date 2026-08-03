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
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->foreign('cliente_id')->references('id')->on('clientes');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->unsignedBigInteger('tipo_documento_comprobante_id')->nullable();
            $table->foreign('tipo_documento_comprobante_id')->references('id')->on('tipo_documento_comprobantes');
            $table->string('numero_comprobante')->nullable();
            $table->decimal('precio_total', 10, 2)->nullable();
            $table->date('fecha_venta')->nullable();
            $table->unsignedBigInteger('tipo_metodo_pago_id')->nullable();
            $table->foreign('tipo_metodo_pago_id')->references('id')->on('tipo_metodo_pagos');
            $table->string('ruta_pdf')->nullable();
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
