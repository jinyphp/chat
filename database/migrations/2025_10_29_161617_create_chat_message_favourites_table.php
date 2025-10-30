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
        Schema::create('chat_message_favourites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->string('user_uuid', 64);
            $table->unsignedBigInteger('room_id');
            $table->string('room_uuid', 64);
            $table->integer('shard_id')->nullable();
            $table->timestamps();

            // 인덱스 및 제약 조건
            $table->unique(['message_id', 'user_uuid'], 'unique_message_user_favourite');
            $table->index(['user_uuid', 'room_id']);
            $table->index(['room_id']);
            $table->index(['created_at']);

            // 외래 키 제약 조건
            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
            $table->foreign('room_id')->references('id')->on('chat_rooms')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_message_favourites');
    }
};
