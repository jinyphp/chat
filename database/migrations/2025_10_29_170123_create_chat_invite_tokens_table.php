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
        Schema::create('chat_invite_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('room_id');
            $table->string('room_uuid', 36);
            $table->string('created_by_uuid', 36);
            $table->timestamp('expires_at');
            $table->integer('max_uses')->nullable(); // null = 무제한
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // 추가 정보 저장
            $table->timestamps();

            // 인덱스
            $table->index(['token', 'is_active']);
            $table->index(['room_id', 'is_active']);
            $table->index(['expires_at', 'is_active']);
            $table->index('created_by_uuid');

            // 외래키 (선택적)
            $table->foreign('room_id')->references('id')->on('chat_rooms')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_invite_tokens');
    }
};
