<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 채팅 메시지 테이블
 *
 * [테이블 역할]
 * - 채팅 메시지 저장 및 관리
 * - 메시지 타입별 처리 (텍스트, 이미지, 파일, 시스템)
 * - 메시지 상태 및 읽음 처리
 * - 샤딩된 사용자 시스템 지원
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            // 기본 필드
            $table->id();
            $table->timestamps();

            // 관계 정보
            $table->foreignId('room_id')->constrained('chat_rooms')->onDelete('cascade')->comment('채팅방 ID');
            $table->string('room_uuid', 36)->index()->comment('채팅방 UUID (캐시용)');

            // 발신자 정보 (샤딩 지원)
            $table->string('sender_uuid', 36)->nullable()->index()->comment('발신자 UUID');
            $table->integer('sender_shard_id')->nullable()->index()->comment('발신자 샤드 ID');
            $table->string('sender_email')->nullable()->comment('발신자 이메일 (캐시)');
            $table->string('sender_name')->nullable()->comment('발신자 이름 (캐시)');
            $table->string('sender_avatar')->nullable()->comment('발신자 아바타 (캐시)');

            // 메시지 내용
            $table->enum('type', ['text', 'image', 'file', 'voice', 'video', 'system', 'notification'])
                  ->default('text')->index()->comment('메시지 타입');
            $table->text('content')->nullable()->comment('메시지 내용 (텍스트)');
            $table->text('encrypted_content')->nullable()->comment('암호화된 메시지 내용');
            $table->json('media')->nullable()->comment('미디어 정보 (이미지, 파일 등)');
            $table->json('metadata')->nullable()->comment('메시지 메타데이터');

            // 답장/스레드 정보
            $table->bigInteger('reply_to_message_id')->nullable()->index()->comment('답장 대상 메시지 ID');
            $table->bigInteger('thread_root_id')->nullable()->index()->comment('스레드 루트 메시지 ID');
            $table->integer('thread_count')->default(0)->comment('스레드 답글 수');

            // 메시지 상태
            $table->enum('status', ['sent', 'delivered', 'read', 'edited', 'deleted'])
                  ->default('sent')->index()->comment('메시지 상태');
            $table->boolean('is_edited')->default(false)->comment('편집 여부');
            $table->boolean('is_deleted')->default(false)->comment('삭제 여부');
            $table->boolean('is_pinned')->default(false)->comment('고정 여부');
            $table->boolean('is_system')->default(false)->index()->comment('시스템 메시지 여부');

            // 편집 및 삭제 추적
            $table->timestamp('edited_at')->nullable()->comment('편집 시간');
            $table->string('edited_by_uuid', 36)->nullable()->comment('편집한 사용자 UUID');
            $table->timestamp('deleted_at')->nullable()->comment('삭제 시간');
            $table->string('deleted_by_uuid', 36)->nullable()->comment('삭제한 사용자 UUID');
            $table->text('delete_reason')->nullable()->comment('삭제 사유');

            // 읽음 처리
            $table->integer('read_count')->default(0)->comment('읽은 사용자 수');
            $table->timestamp('first_read_at')->nullable()->comment('첫 읽음 시간');
            $table->timestamp('last_read_at')->nullable()->comment('마지막 읽음 시간');

            // 반응 및 상호작용
            $table->json('reactions')->nullable()->comment('메시지 반응 (이모지 등)');
            $table->integer('reaction_count')->default(0)->comment('총 반응 수');

            // 멘션 및 태그
            $table->json('mentions')->nullable()->comment('멘션된 사용자들');
            $table->json('tags')->nullable()->comment('메시지 태그');

            // 복합 인덱스
            $table->index(['room_id', 'created_at']); // 방별 시간순 조회
            $table->index(['sender_uuid', 'sender_shard_id']); // 발신자별 조회
            $table->index(['room_uuid', 'type', 'status']); // 방별 타입/상태별 조회
            $table->index(['status', 'created_at']); // 상태별 시간순 조회
            $table->index(['is_system', 'room_id']); // 시스템 메시지 조회
            $table->index(['thread_root_id', 'created_at']); // 스레드별 조회
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};