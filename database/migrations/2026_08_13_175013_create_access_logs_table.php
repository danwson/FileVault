<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            // cascadeOnDelete: o log é um histórico da vida do arquivo, não
            // sobrevive à exclusão dele (simplificação deliberada pra essa
            // etapa — um log de auditoria "permanente" de verdade exigiria
            // file_id nullable + snapshot dos metadados no próprio log).
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            // nullOnDelete: se o ShareLink em si for apagado no futuro, o
            // log continua existindo (só perde essa referência específica).
            $table->foreignId('share_link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['file_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
