<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 채팅 메시지 읽음 상태 테이블
 *
 * [테이블 역할]
 * - 사용자별 메시지 읽음 상태 추적
 * - 읽지 않은 메시지 수 계산
 * - 메시지 전달 확인
 * - 샤딩된 사용자 시스템 지원
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_message_reads', function (Blueprint $table) {
            // 기본 필드
            $table->id();
            $table->timestamps();

            // 관계 정보
            $table->foreignId('message_id')->constrained('chat_messages')->onDelete('cascade')->comment('메시지 ID');
            $table->foreignId('room_id')->constrained('chat_rooms')->onDelete('cascade')->comment('채팅방 ID');

            // 읽은 사용자 정보 (샤딩 지원)
            $table->string('user_uuid', 36)->index()->comment('사용자 UUID');
            $table->integer('shard_id')->index()->comment('사용자 샤드 ID');
            $table->string('user_email')->nullable()->comment('사용자 이메일 (캐시)');
            $table->string('user_name')->nullable()->comment('사용자 이름 (캐시)');

            // 읽음 정보
            $table->timestamp('read_at')->comment('읽은 시간');
            $table->enum('read_type', ['delivered', 'read', 'seen'])
                  ->default('read')->comment('읽음 타입');

            // 디바이스 정보
            $table->string('device_type')->nullable()->comment('디바이스 타입 (web, mobile, desktop)');
            $table->string('user_agent')->nullable()->comment('User Agent');
            $table->string('ip_address', 45)->nullable()->comment('IP 주소');

            // 복합 인덱스 및 제약조건
            $table->unique(['message_id', 'user_uuid'], 'unique_message_user_read');
            $table->index(['user_uuid', 'shard_id']);
            $table->index(['room_id', 'user_uuid']);
            $table->index(['message_id', 'read_at']);
            $table->index(['shard_id', 'user_uuid', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_message_reads');
    }
};