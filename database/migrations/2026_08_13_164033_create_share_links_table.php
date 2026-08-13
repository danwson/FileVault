<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            // Token público usado na URL — nunca o id do arquivo/registro,
            // pra não dar pra adivinhar/enumerar outros links.
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            // Null = sem limite de usos (só a expiração vale).
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_links');
    }
};
