<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_message_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->string('source_language', 10);
            $table->string('target_language', 10);
            $table->text('original_content');
            $table->text('translated_content');
            $table->string('translation_provider', 50)->default('google'); // google, deepl, etc
            $table->json('translation_metadata')->nullable(); // 번역 품질, 신뢰도 등
            $table->boolean('is_valid')->default(true);
            $table->timestamp('translated_at');
            $table->timestamps();

            // 인덱스
            $table->unique(['message_id', 'target_language']); // 메시지-언어 조합은 고유
            $table->index(['message_id', 'source_language']);
            $table->index(['target_language', 'is_valid']);
            $table->index('translated_at');

            // 외래키
            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_message_translations');
    }
};
