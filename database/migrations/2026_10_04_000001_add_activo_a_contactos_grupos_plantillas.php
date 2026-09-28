<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes, grupos y plantillas ya no se eliminan: se desactivan y conservan su historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('acepta_correos');
        });
        Schema::table('grupos', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('sede_id');
        });
        Schema::table('plantillas', function (Blueprint $table) {
            $table->boolean('activa')->default(true)->after('predeterminada');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', fn (Blueprint $table) => $table->dropColumn('activo'));
        Schema::table('grupos', fn (Blueprint $table) => $table->dropColumn('activo'));
        Schema::table('plantillas', fn (Blueprint $table) => $table->dropColumn('activa'));
    }
};
