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
        Schema::create('usuarios', function (Blueprint $table) {
            
            $table->id();
            $table->unsignedBigInteger('tipo_documento_identidad_id')->nullable();
            $table->foreign('tipo_documento_identidad_id')->references('id')->on('tipo_documento_identidades');
            $table->string('numero_documento')->nullable();
            $table->string('nombres')->nullable();
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->string('numero_celular')->nullable();
            $table->unsignedBigInteger('departamento_id')->nullable();
            $table->foreign('departamento_id')->references('id')->on('departamentos');
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id')->references('id')->on('provincias');
            $table->unsignedBigInteger('distrito_id')->nullable();
            $table->foreign('distrito_id')->references('id')->on('distritos');
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedBigInteger('genero_id')->nullable();
            $table->foreign('genero_id')->references('id')->on('generos');
            $table->string('profile_photo_path', 2048)->nullable();            
            $table->string('correo')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->unsignedBigInteger('rol_id')->nullable();
            $table->foreign('rol_id')->references('id')->on('roles');
            $table->boolean('acepto_termino_condiciones')->default(false);
            $table->integer('estado')->default(1)->comment('0=inactivo, 1=activo');
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
