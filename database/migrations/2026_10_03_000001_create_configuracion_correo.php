<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de envío de correo editables desde el sistema (un solo registro).
 * Si no existe el registro, se usan los valores MAIL_* del .env.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_correo', function (Blueprint $table) {
            $table->id();
            $table->string('modo', 10)->default('smtp');      // smtp (envío real) | log (modo de prueba)
            $table->string('host', 150)->nullable();
            $table->unsignedSmallInteger('puerto')->nullable();
            $table->string('cifrado', 10)->default('tls');    // tls (STARTTLS, puerto 587) | ssl (puerto 465)
            $table->string('usuario', 150)->nullable();
            $table->text('password')->nullable();              // cifrada con APP_KEY
            $table->string('remitente_correo', 150);
            $table->string('remitente_nombre', 100);
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_correo');
    }
};
