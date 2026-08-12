<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->foreign('producto_id')->references('id')->on('productos');
            $table->unsignedBigInteger('tipo_movimiento_inventario_id')->nullable();
            $table->foreign('tipo_movimiento_inventario_id')->references('id')->on('tipo_movimiento_inventarios');
            $table->integer('cantidad')->nullable();
            $table->string('tipo_referencia')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable(); 
            $table->string('motivo', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->integer('estado')->default(1)->comment('0=inactivo, 1=activo');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};