<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_carritos', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('carrito_id')->nullable();
            $table->foreign('carrito_id')->references('id')->on('carritos');
            
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->foreign('producto_id')->references('id')->on('productos');
            
            $table->integer('cantidad');
            $table->integer('estado')->default(1)->comment('0=inactivo, 1=activo');

            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_carritos');
    }
};