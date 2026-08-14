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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->foreign('categoria_id')->references('id')->on('categorias');
            $table->unsignedBigInteger('marca_id')->nullable();
            $table->foreign('marca_id')->references('id')->on('marcas');
            $table->string('nombre', 150);
            $table->string('slug', 200)->unique();
            $table->string('descripcion_corta', 255)->nullable();
            $table->text('descripcion_larga')->nullable();
            $table->decimal('precio_venta', 10, 2);
            $table->decimal('precio_oferta', 10, 2)->nullable();
            $table->decimal('precio_compra_referencial', 10, 2)->nullable();
            $table->boolean('es_destacado')->default(false);
            $table->integer('stock_actual')->default(0);
            $table->string('codigo_barras', 100)->unique()->nullable();
            $table->string('codigo_qr', 255)->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->integer('estado')->default(1)->comment('0=inactivo, 1=activo');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
