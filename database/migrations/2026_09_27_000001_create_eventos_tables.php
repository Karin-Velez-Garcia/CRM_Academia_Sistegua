<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reuniones con padres de familia y capacitaciones para catedráticos.
     * Ambas pueden ser presenciales (lugar) o virtuales (enlace).
     */
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);            // reunion | capacitacion
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->foreignId('sede_id')->constrained('sedes');
            $table->string('modalidad', 20);       // presencial | virtual
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->string('lugar', 200)->nullable();   // presencial: salón y dirección
            $table->string('enlace', 500)->nullable();  // virtual: Zoom, Meet, Teams…
            $table->string('facilitador', 150)->nullable();      // capacitación: quien la imparte
            $table->unsignedSmallInteger('cupo')->nullable();     // capacitación: máximo de participantes
            $table->boolean('para_todos')->default(false);        // todos los padres/catedráticos de la sede
            $table->timestamp('cancelado_at')->nullable();
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tipo', 'inicio']);
            $table->index(['sede_id', 'inicio']);
        });

        // Grupos a los que va dirigido el evento (base para las invitaciones)
        Schema::create('evento_grupo', function (Blueprint $table) {
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->primary(['evento_id', 'grupo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_grupo');
        Schema::dropIfExists('eventos');
    }
};
