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
        Schema::create('comprobante_ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id')->unique()->nullable();
            $table->foreign('venta_id')->references('id')->on('ventas');
            $table->unsignedBigInteger('tipo_documento_comprobante_id')->nullable();
            $table->foreign('tipo_documento_comprobante_id')->references('id')->on('tipo_documento_comprobantes');
            $table->string('serie_comprobante', 10)->nullable();
            $table->string('numero_comprobante', 20)->nullable();
            $table->string('ruta_pdf_xml', 500)->nullable();
            $table->string('estado_comprobante', 30)->default('EMITIDO');
            $table->timestamp('fecha_emision')->nullable();
            $table->integer('estado')->default(1)->comment('0=inactivo, 1=activo');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('comprobante_ventas');
    }
};