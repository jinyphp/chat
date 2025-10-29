<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ChatMessageTranslation 모델 - 채팅 메시지 번역 저장 및 관리
 *
 * [모델 역할 및 목적]
 * - 채팅 메시지의 번역 결과를 데이터베이스에 저장
 * - 번역 재사용으로 성능 최적화 및 비용 절감
 * - 번역 품질 관리 및 메타데이터 저장
 * - 번역 제공자별 성능 추적
 *
 * [주요 컬럼]
 * - message_id: 원본 메시지 ID
 * - source_language: 원본 언어 코드
 * - target_language: 번역 대상 언어 코드
 * - original_content: 원본 텍스트
 * - translated_content: 번역된 텍스트
 * - translation_provider: 번역 제공자 (google, deepl 등)
 * - translation_metadata: 번역 메타데이터 (품질, 신뢰도 등)
 * - is_valid: 번역 유효성
 * - translated_at: 번역 수행 시각
 *
 * [사용 예시]
 * ```php
 * // 번역 저장
 * ChatMessageTranslation::storeTranslation($messageId, 'en', 'ko', $original, $translated);
 *
 * // 번역 조회
 * $translation = ChatMessageTranslation::getTranslation($messageId, 'ko');
 *
 * // 다중 메시지 번역 조회
 * $translations = ChatMessageTranslation::getTranslationsForMessages($messageIds, 'ko');
 * ```
 */
class ChatMessageTranslation extends Model
{
    use HasFactory;

    protected $table = 'chat_message_translations';

    protected $fillable = [
        'message_id',
        'source_language',
        'target_language',
        'original_content',
        'translated_content',
        'translation_provider',
        'translation_metadata',
        'is_valid',
        'translated_at',
    ];

    protected $casts = [
        'translation_metadata' => 'array',
        'is_valid' => 'boolean',
        'translated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 원본 메시지와의 관계
     */
    public function message()
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    /**
     * 번역 저장
     */
    public static function storeTranslation(
        $messageId,
        $sourceLanguage,
        $targetLanguage,
        $originalContent,
        $translatedContent,
        $provider = 'google',
        $metadata = []
    ) {
        return static::updateOrCreate(
            [
                'message_id' => $messageId,
                'target_language' => $targetLanguage,
            ],
            [
                'source_language' => $sourceLanguage,
                'original_content' => $originalContent,
                'translated_content' => $translatedContent,
                'translation_provider' => $provider,
                'translation_metadata' => $metadata,
                'is_valid' => true,
                'translated_at' => now(),
            ]
        );
    }

    /**
     * 특정 메시지의 번역 조회
     */
    public static function getTranslation($messageId, $targetLanguage)
    {
        return static::where('message_id', $messageId)
            ->where('target_language', $targetLanguage)
            ->where('is_valid', true)
            ->first();
    }

    /**
     * 다중 메시지의 번역 조회
     */
    public static function getTranslationsForMessages($messageIds, $targetLanguage)
    {
        if (empty($messageIds)) {
            return collect();
        }

        return static::whereIn('message_id', $messageIds)
            ->where('target_language', $targetLanguage)
            ->where('is_valid', true)
            ->get()
            ->keyBy('message_id');
    }

    /**
     * 메시지에 대한 모든 번역 조회
     */
    public static function getAllTranslationsForMessage($messageId)
    {
        return static::where('message_id', $messageId)
            ->where('is_valid', true)
            ->get()
            ->keyBy('target_language');
    }

    /**
     * 번역이 필요한 메시지 필터링
     */
    public static function filterUntranslatedMessages($messageIds, $targetLanguage)
    {
        if (empty($messageIds)) {
            return [];
        }

        $existingTranslations = static::whereIn('message_id', $messageIds)
            ->where('target_language', $targetLanguage)
            ->where('is_valid', true)
            ->pluck('message_id')
            ->toArray();

        return array_diff($messageIds, $existingTranslations);
    }

    /**
     * 번역 무효화
     */
    public function invalidate()
    {
        $this->update(['is_valid' => false]);
    }

    /**
     * 번역 품질 업데이트
     */
    public function updateQuality($quality, $feedback = null)
    {
        $metadata = $this->translation_metadata ?? [];
        $metadata['quality'] = $quality;
        if ($feedback) {
            $metadata['feedback'] = $feedback;
        }
        $metadata['updated_at'] = now()->toISOString();

        $this->update(['translation_metadata' => $metadata]);
    }

    /**
     * 번역 제공자별 통계
     */
    public static function getProviderStats($days = 30)
    {
        return static::where('created_at', '>=', now()->subDays($days))
            ->where('is_valid', true)
            ->selectRaw('
                translation_provider,
                COUNT(*) as total_translations,
                COUNT(DISTINCT message_id) as unique_messages,
                COUNT(DISTINCT target_language) as target_languages
            ')
            ->groupBy('translation_provider')
            ->get();
    }

    /**
     * 언어별 번역 통계
     */
    public static function getLanguageStats($days = 30)
    {
        return static::where('created_at', '>=', now()->subDays($days))
            ->where('is_valid', true)
            ->selectRaw('
                source_language,
                target_language,
                COUNT(*) as translation_count
            ')
            ->groupBy(['source_language', 'target_language'])
            ->orderBy('translation_count', 'desc')
            ->get();
    }

    /**
     * 오래된 번역 정리
     */
    public static function cleanupOldTranslations($days = 365)
    {
        return static::where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * 메시지 삭제 시 관련 번역도 삭제
     */
    public static function deleteTranslationsForMessage($messageId)
    {
        return static::where('message_id', $messageId)->delete();
    }

    /**
     * 번역 결과를 배열로 포맷
     */
    public function toTranslationArray()
    {
        return [
            'success' => true,
            'original' => $this->original_content,
            'translated' => $this->translated_content,
            'source_language' => $this->source_language,
            'target_language' => $this->target_language,
            'needs_translation' => true,
            'provider' => $this->translation_provider,
            'translated_at' => $this->translated_at,
            'metadata' => $this->translation_metadata,
        ];
    }

    /**
     * 번역 없음을 나타내는 배열
     */
    public static function noTranslationArray($original, $sourceLanguage, $targetLanguage)
    {
        return [
            'success' => true,
            'original' => $original,
            'translated' => $original,
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'needs_translation' => false,
        ];
    }
}