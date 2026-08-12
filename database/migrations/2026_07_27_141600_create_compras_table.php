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
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->foreign('proveedor_id')->references('id')->on('proveedores');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('id')->on('usuarios');
            
            $table->unsignedBigInteger('tipo_documento_comprobante_id')->nullable();
            $table->foreign('tipo_documento_comprobante_id')->references('id')->on('tipo_documento_comprobantes');
            $table->string('numero_comprobante')->nullable();
            $table->decimal('costo_total', 10, 2)->nullable();
            $table->date('fecha_compra')->nullable();
            $table->string('ruta_pdf')->nullable();
            $table->integer('estado')->default(1)->comment('0=inactivo, 1=activo');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
