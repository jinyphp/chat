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
        Schema::table('chat_message_translations', function (Blueprint $table) {
            // 외래키 제약조건 제거 (채팅방별 독립 DB 구조 때문)
            $table->dropForeign(['message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_message_translations', function (Blueprint $table) {
            // 외래키 제약조건 복구
            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
        });
    }
};
