<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La invitación también guarda la asistencia. Las personas sin correo o que llegaron sin invitación
     * se registran como invitaciones "no enviadas" (correo vacío) para que cuenten en la asistencia.
     */
    public function up(): void
    {
        Schema::table('invitaciones', function (Blueprint $table) {
            $table->string('correo', 150)->nullable()->change();
            $table->boolean('asistio')->nullable()->after('respuesta_por');
            $table->timestamp('asistencia_at')->nullable()->after('asistio');
            $table->foreignId('asistencia_por')->nullable()->after('asistencia_at')->constrained('users')->nullOnDelete();
            $table->string('codigo_constancia', 12)->nullable()->unique()->after('asistencia_por');
        });
    }

    public function down(): void
    {
        Schema::table('invitaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asistencia_por');
            $table->dropColumn(['asistio', 'asistencia_at', 'codigo_constancia']);
        });
    }
};
