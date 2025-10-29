<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 채팅방 참여자 테이블
 *
 * [테이블 역할]
 * - 채팅방별 참여자 관리
 * - 사용자별 참여 상태 추적
 * - 샤딩된 사용자 시스템 완벽 지원
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_participants', function (Blueprint $table) {
            // 기본 필드
            $table->id();
            $table->timestamps();

            // 관계 정보
            $table->foreignId('room_id')->constrained('chat_rooms')->onDelete('cascade')->comment('채팅방 ID');
            $table->string('room_uuid', 36)->index()->comment('채팅방 UUID (캐시용)');

            // 사용자 정보 (샤딩 지원)
            $table->string('user_uuid', 36)->index()->comment('사용자 UUID');
            $table->integer('shard_id')->index()->comment('사용자 샤드 ID (0-15)');
            $table->string('email')->nullable()->comment('사용자 이메일 (캐시)');
            $table->string('name')->nullable()->comment('사용자 이름 (캐시)');
            $table->string('avatar')->nullable()->comment('사용자 아바타 (캐시)');

            // 참여 정보
            $table->enum('role', ['owner', 'admin', 'moderator', 'member'])
                  ->default('member')->comment('방 내 역할');
            $table->enum('status', ['active', 'inactive', 'banned', 'left'])
                  ->default('active')->index()->comment('참여 상태');

            // 권한 설정
            $table->json('permissions')->nullable()->comment('세부 권한 설정');
            $table->boolean('can_send_message')->default(true)->comment('메시지 전송 권한');
            $table->boolean('can_invite')->default(false)->comment('초대 권한');
            $table->boolean('can_moderate')->default(false)->comment('중재 권한');

            // 알림 설정
            $table->boolean('notifications_enabled')->default(true)->comment('알림 활성화');
            $table->json('notification_settings')->nullable()->comment('상세 알림 설정');

            // 메시지 관련
            $table->timestamp('last_read_at')->nullable()->comment('마지막 읽은 시간');
            $table->bigInteger('last_read_message_id')->nullable()->comment('마지막 읽은 메시지 ID');
            $table->integer('unread_count')->default(0)->comment('읽지 않은 메시지 수');

            // 활동 추적
            $table->timestamp('joined_at')->nullable()->comment('참여 시간');
            $table->timestamp('last_seen_at')->nullable()->comment('마지막 접속 시간');
            $table->string('invited_by_uuid', 36)->nullable()->comment('초대한 사용자 UUID');
            $table->text('join_reason')->nullable()->comment('참여 사유/메모');

            // 제재 정보
            $table->timestamp('banned_at')->nullable()->comment('차단 시간');
            $table->string('banned_by_uuid', 36)->nullable()->comment('차단한 관리자 UUID');
            $table->text('ban_reason')->nullable()->comment('차단 사유');
            $table->timestamp('ban_expires_at')->nullable()->comment('차단 만료 시간');

            // 복합 인덱스
            $table->unique(['room_id', 'user_uuid'], 'unique_room_user');
            $table->index(['user_uuid', 'shard_id']);
            $table->index(['room_uuid', 'status']);
            $table->index(['status', 'last_seen_at']);
            $table->index(['shard_id', 'user_uuid', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_participants');
    }
};