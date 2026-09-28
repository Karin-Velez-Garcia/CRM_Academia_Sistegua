<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Textos de correo reutilizables, con variables como {nombre} o {fecha}
        Schema::create('plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('tipo_evento', 20);   // reunion | capacitacion
            $table->string('asunto', 200);
            $table->text('mensaje');
            $table->boolean('predeterminada')->default(false);
            $table->timestamps();
        });

        // Cada vez que se manda un lote de correos de un evento (historial)
        Schema::create('envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->string('motivo', 20);        // invitacion | recordatorio | cambio | cancelacion
            $table->string('asunto', 200);
            $table->text('mensaje')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Una invitación por persona y evento: su envío, su respuesta y (en la fase 5) su asistencia
        Schema::create('invitaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('contacto_id')->constrained('contactos')->cascadeOnDelete();
            $table->uuid('token')->unique();
            $table->string('correo', 150);                    // correo al que se envió
            $table->string('estado_envio', 20)->default('pendiente'); // pendiente | enviada | fallida
            $table->text('error')->nullable();
            $table->string('respuesta', 20)->nullable();      // confirmada | rechazada
            $table->string('comentario', 500)->nullable();    // mensaje del invitado al responder
            $table->string('respuesta_por', 20)->nullable();  // invitado | personal
            $table->timestamp('enviada_at')->nullable();
            $table->timestamp('vista_at')->nullable();        // abrió la página de la invitación
            $table->timestamp('respondida_at')->nullable();
            $table->timestamp('recordatorio_at')->nullable();
            $table->timestamps();

            $table->unique(['evento_id', 'contacto_id']);
            $table->index(['evento_id', 'respuesta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitaciones');
        Schema::dropIfExists('envios');
        Schema::dropIfExists('plantillas');
    }
};
