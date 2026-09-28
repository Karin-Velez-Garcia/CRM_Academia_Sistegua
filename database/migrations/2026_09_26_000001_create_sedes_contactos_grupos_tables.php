<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->string('direccion', 200)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 150)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // La sede de un usuario limita lo que ve (null = todas las sedes)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('sede_id')->nullable()->after('telefono')->constrained('sedes')->nullOnDelete();
        });

        // Padres de familia y catedráticos comparten tabla; "tipo" los distingue
        Schema::create('contactos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // padre | catedratico
            $table->foreignId('sede_id')->constrained('sedes');
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('dpi', 13)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('estudiante', 150)->nullable();    // padres: nombre del estudiante
            $table->string('grado_seccion', 60)->nullable();  // padres: grado y sección
            $table->string('area', 100)->nullable();          // catedráticos: curso o área
            $table->boolean('acepta_correos')->default(true);
            $table->uuid('token')->unique();                   // enlaces de confirmación y baja
            $table->timestamps();

            $table->unique(['tipo', 'correo']);
            $table->unique(['tipo', 'dpi']);
            $table->index(['tipo', 'sede_id']);
            $table->index(['apellidos', 'nombres']);
        });

        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->string('tipo', 20); // padre | catedratico | mixto
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete(); // null = todas las sedes
            $table->timestamps();

            $table->unique(['nombre', 'sede_id']);
        });

        Schema::create('contacto_grupo', function (Blueprint $table) {
            $table->foreignId('contacto_id')->constrained('contactos')->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->primary(['contacto_id', 'grupo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacto_grupo');
        Schema::dropIfExists('grupos');
        Schema::dropIfExists('contactos');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sede_id');
        });
        Schema::dropIfExists('sedes');
    }
};
