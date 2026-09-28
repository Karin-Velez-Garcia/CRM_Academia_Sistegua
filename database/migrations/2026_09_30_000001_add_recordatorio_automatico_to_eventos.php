<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('recordatorio_automatico')->default(true)->after('para_todos');
            $table->timestamp('recordatorio_enviado_at')->nullable()->after('recordatorio_automatico');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['recordatorio_automatico', 'recordatorio_enviado_at']);
        });
    }
};
