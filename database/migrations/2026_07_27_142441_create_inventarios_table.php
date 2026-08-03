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
            
            // 1. Corregido: En singular, como lo tenías, pero ahora coincidirá con el código
            $table->unsignedBigInteger('tipo_movimiento_inventario_id')->nullable();
            $table->foreign('tipo_movimiento_inventario_id')->references('id')->on('tipo_movimiento_inventarios');
            
            $table->integer('cantidad')->nullable();
            $table->string('tipo_referencia')->nullable();
            
            // 2. Corregido: Se quitó la llave foránea a 'productos' para que acepte Compras y Ventas libremente
            $table->unsignedBigInteger('referencia_id')->nullable(); 
            
            // 3. Nuevos: Agregamos los campos que nos faltaban para la auditoría
            $table->string('motivo', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable(); // Si deseas le pones su foreign key a tu tabla de usuarios
            
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