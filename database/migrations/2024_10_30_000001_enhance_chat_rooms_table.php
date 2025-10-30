<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 채팅방 테이블 개선
 *
 * [개선 사항]
 * - 카테고리 및 태그 시스템
 * - 고급 설정 옵션들
 * - 관리 기능 강화
 * - 알림 및 모더레이션 설정
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            // 카테고리 및 분류
            $table->string('category', 50)->nullable()->after('type')->comment('방 카테고리');
            $table->json('tags')->nullable()->after('category')->comment('방 태그 목록');

            // 고급 설정
            $table->integer('message_retention_days')->default(0)->after('max_participants')->comment('메시지 보관 기간 (0=무제한)');
            $table->boolean('allow_file_upload')->default(true)->after('allow_invite')->comment('파일 업로드 허용');
            $table->boolean('allow_voice_message')->default(true)->after('allow_file_upload')->comment('음성 메시지 허용');
            $table->boolean('allow_image_upload')->default(true)->after('allow_voice_message')->comment('이미지 업로드 허용');
            $table->integer('max_file_size_mb')->default(10)->after('allow_image_upload')->comment('최대 파일 크기 (MB)');

            // 모더레이션 설정
            $table->boolean('require_approval')->default(false)->after('max_file_size_mb')->comment('참여 승인 필요');
            $table->boolean('auto_moderation')->default(false)->after('require_approval')->comment('자동 모더레이션 활성화');
            $table->json('blocked_words')->nullable()->after('auto_moderation')->comment('금지어 목록');
            $table->integer('slow_mode_seconds')->default(0)->after('blocked_words')->comment('슬로우 모드 (초)');

            // 알림 설정
            $table->json('notification_settings')->nullable()->after('ui_settings')->comment('알림 설정');

            // 방 옵션들
            $table->boolean('show_member_list')->default(true)->after('notification_settings')->comment('멤버 목록 표시');
            $table->boolean('allow_mentions')->default(true)->after('show_member_list')->comment('멘션 허용');
            $table->boolean('allow_reactions')->default(true)->after('allow_mentions')->comment('리액션 허용');
            $table->boolean('read_receipts')->default(true)->after('allow_reactions')->comment('읽음 확인 표시');

            // 통계 및 분석
            $table->integer('daily_message_limit')->default(0)->after('message_count')->comment('일일 메시지 제한 (0=무제한)');
            $table->integer('today_message_count')->default(0)->after('daily_message_limit')->comment('오늘 메시지 수');
            $table->date('last_reset_date')->nullable()->after('today_message_count')->comment('마지막 카운트 리셋 날짜');

            // 추가 인덱스
            $table->index('category');
            $table->index(['category', 'type', 'status']);
            $table->index(['require_approval', 'allow_join']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            // 인덱스 제거
            $table->dropIndex(['category']);
            $table->dropIndex(['category', 'type', 'status']);
            $table->dropIndex(['require_approval', 'allow_join']);

            // 컬럼 제거
            $table->dropColumn([
                'category',
                'tags',
                'message_retention_days',
                'allow_file_upload',
                'allow_voice_message',
                'allow_image_upload',
                'max_file_size_mb',
                'require_approval',
                'auto_moderation',
                'blocked_words',
                'slow_mode_seconds',
                'notification_settings',
                'show_member_list',
                'allow_mentions',
                'allow_reactions',
                'read_receipts',
                'daily_message_limit',
                'today_message_count',
                'last_reset_date',
            ]);
        });
    }
};