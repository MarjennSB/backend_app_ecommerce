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
        Schema::create('transacciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->unsignedBigInteger('tipo_transaccion_id')->nullable();
            $table->foreign('tipo_transaccion_id')->references('id')->on('tipo_transacciones');
            $table->string('tipo_referencia')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->decimal('monto', 10, 2)->nullable();
            $table->string('motivo')->nullable();
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
        Schema::dropIfExists('transacciones');
    }
};