<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * QR de asistencia:
     *  - eventos.token_registro: enlace del QR del evento (auto-registro de los asistentes).
     *  - invitaciones.asistencia_metodo: cómo se marcó la asistencia (manual | qr_evento | qr_personal).
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->uuid('token_registro')->nullable()->unique()->after('recordatorio_enviado_at');
        });
        DB::table('eventos')->whereNull('token_registro')->orderBy('id')->each(function ($e) {
            DB::table('eventos')->where('id', $e->id)->update(['token_registro' => (string) Str::uuid()]);
        });

        Schema::table('invitaciones', function (Blueprint $table) {
            $table->string('asistencia_metodo', 20)->nullable()->after('asistencia_por');
        });
        DB::table('invitaciones')->whereNotNull('asistio')->update(['asistencia_metodo' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('invitaciones', fn (Blueprint $t) => $t->dropColumn('asistencia_metodo'));
        Schema::table('eventos', fn (Blueprint $t) => $t->dropColumn('token_registro'));
    }
};
