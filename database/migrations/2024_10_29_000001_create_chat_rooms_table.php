<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 채팅방 테이블
 *
 * [테이블 역할]
 * - 채팅방 정보 관리
 * - 방 설정 및 메타데이터 저장
 * - 샤딩된 사용자 시스템 지원
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            // 기본 필드
            $table->id();
            $table->timestamps();

            // 방 식별자
            $table->string('uuid', 36)->unique()->index()->comment('방 고유 식별자');
            $table->string('code', 50)->unique()->index()->comment('방 코드 (사용자 친화적)');
            $table->string('slug', 100)->nullable()->index()->comment('방 슬러그 (URL 친화적)');

            // 방 정보
            $table->string('title')->comment('방 제목');
            $table->text('description')->nullable()->comment('방 설명');
            $table->string('image')->nullable()->comment('방 이미지');
            $table->string('type', 20)->default('public')->comment('방 타입 (public, private, group, direct)');

            // 보안 설정
            $table->string('password')->nullable()->comment('방 비밀번호 (해시됨)');
            $table->string('invite_code', 50)->nullable()->unique()->comment('초대 코드');
            $table->string('encryption_key')->nullable()->comment('메시지 암호화 키');

            // 방장 정보 (샤딩 지원)
            $table->string('owner_uuid', 36)->nullable()->index()->comment('방장 UUID');
            $table->integer('owner_shard_id')->nullable()->index()->comment('방장 샤드 ID');
            $table->string('owner_email')->nullable()->comment('방장 이메일 (캐시)');
            $table->string('owner_name')->nullable()->comment('방장 이름 (캐시)');

            // 방 통계
            $table->integer('participant_count')->default(0)->comment('참여자 수');
            $table->integer('max_participants')->default(100)->comment('최대 참여자 수');
            $table->integer('message_count')->default(0)->comment('총 메시지 수');

            // 상태 관리
            $table->enum('status', ['active', 'inactive', 'archived', 'deleted'])
                  ->default('active')->index()->comment('방 상태');
            $table->boolean('is_public')->default(true)->index()->comment('공개 여부');
            $table->boolean('allow_join')->default(true)->comment('참여 허용 여부');
            $table->boolean('allow_invite')->default(true)->comment('초대 허용 여부');

            // UI 설정
            $table->json('ui_settings')->nullable()->comment('UI 설정 (색상, 테마 등)');

            // 시간 관련
            $table->timestamp('last_activity_at')->nullable()->index()->comment('마지막 활동 시간');
            $table->timestamp('last_message_at')->nullable()->index()->comment('마지막 메시지 시간');
            $table->timestamp('expires_at')->nullable()->index()->comment('만료 시간 (임시 방용)');

            // 인덱스
            $table->index(['type', 'status', 'is_public']);
            $table->index(['owner_uuid', 'owner_shard_id']);
            $table->index(['status', 'last_activity_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};