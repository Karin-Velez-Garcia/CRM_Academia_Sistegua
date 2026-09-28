<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diseños de certificado editables desde el sistema. Cada diseño guarda sus elementos
 * (textos, imágenes, líneas) con posición y estilo; el PDF se arma a partir de eso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_certificado', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->boolean('predeterminada')->default(false);
            $table->string('orientacion', 20)->default('horizontal'); // horizontal | vertical
            $table->string('fondo', 255)->nullable();                 // imagen de fondo a página completa
            $table->json('elementos');
            $table->timestamps();
        });

        // Cada capacitación puede usar un diseño propio; si no, se usa el predeterminado
        Schema::table('eventos', function (Blueprint $table) {
            $table->foreignId('plantilla_certificado_id')->nullable()->after('facilitador')
                ->constrained('plantillas_certificado')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plantilla_certificado_id');
        });
        Schema::dropIfExists('plantillas_certificado');
    }
};
