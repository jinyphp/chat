<?php

namespace Jiny\Chat\Services;

use Stichoza\GoogleTranslate\GoogleTranslate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Jiny\Chat\Models\ChatMessageTranslation;

/**
 * TranslationService - 구글 번역을 이용한 메시지 번역 서비스
 *
 * [서비스 역할 및 목적]
 * - 채팅 메시지의 다국어 번역 처리
 * - 구글 번역 API를 통한 실시간 번역
 * - 번역 결과 캐싱으로 성능 최적화
 * - 언어 감지 및 번역 필요성 판단
 *
 * [주요 기능]
 * - 메시지 언어 자동 감지
 * - 대상 언어로 번역
 * - 번역 결과 캐싱
 * - 번역 실패 시 graceful fallback
 *
 * [사용 예시]
 * ```php
 * $translator = app(TranslationService::class);
 *
 * // 메시지 번역
 * $result = $translator->translateMessage('Hello world', 'ko');
 *
 * // 번역 필요성 확인
 * $needsTranslation = $translator->needsTranslation('en', 'ko');
 * ```
 */
class TranslationService
{
    protected $translator;
    protected $cachePrefix = 'chat_translation';
    protected $cacheExpiry = 24 * 60 * 60; // 24시간

    public function __construct()
    {
        $this->translator = new GoogleTranslate();
    }

    /**
     * 채팅 메시지 번역 (데이터베이스 우선)
     *
     * @param int $messageId 메시지 ID
     * @param string $text 번역할 텍스트
     * @param string $targetLanguage 대상 언어 코드
     * @param string|null $sourceLanguage 원본 언어 코드 (null이면 자동 감지)
     * @return array 번역 결과
     */
    public function translateChatMessage($messageId, $text, $targetLanguage, $sourceLanguage = null)
    {
        // 빈 텍스트 처리
        if (empty(trim($text))) {
            return ChatMessageTranslation::noTranslationArray($text, $sourceLanguage, $targetLanguage);
        }

        // 데이터베이스에서 기존 번역 확인
        $existingTranslation = ChatMessageTranslation::getTranslation($messageId, $targetLanguage);
        if ($existingTranslation) {
            Log::info('기존 번역 사용', [
                'message_id' => $messageId,
                'target_language' => $targetLanguage,
                'translation_id' => $existingTranslation->id
            ]);
            return $existingTranslation->toTranslationArray();
        }

        // 원본 언어 감지 (지정되지 않은 경우)
        if (!$sourceLanguage) {
            $detectedLanguage = $this->detectLanguage($text);
            $sourceLanguage = $detectedLanguage ?: 'auto';
        }

        // 번역 필요성 확인
        if (!$this->needsTranslation($sourceLanguage, $targetLanguage)) {
            // 데이터베이스에 번역 불필요 정보 저장
            ChatMessageTranslation::storeTranslation(
                $messageId,
                $sourceLanguage,
                $targetLanguage,
                $text,
                $text, // 동일한 내용
                'none', // 번역 제공자: 번역 안함
                ['needs_translation' => false]
            );

            return ChatMessageTranslation::noTranslationArray($text, $sourceLanguage, $targetLanguage);
        }

        // 구글 번역 실행
        try {
            $this->translator->setSource($sourceLanguage);
            $this->translator->setTarget($targetLanguage);

            $translatedText = $this->translator->translate($text);

            // 번역 결과를 데이터베이스에 저장
            $translation = ChatMessageTranslation::storeTranslation(
                $messageId,
                $sourceLanguage,
                $targetLanguage,
                $text,
                $translatedText,
                'google',
                [
                    'api_version' => '1.0',
                    'confidence' => 'auto',
                    'translated_at' => now()->toISOString()
                ]
            );

            Log::info('새 번역 생성 및 저장', [
                'message_id' => $messageId,
                'original' => $text,
                'translated' => $translatedText,
                'from' => $sourceLanguage,
                'to' => $targetLanguage,
                'translation_id' => $translation->id
            ]);

            return $translation->toTranslationArray();

        } catch (\Exception $e) {
            Log::error('메시지 번역 실패', [
                'message_id' => $messageId,
                'text' => $text,
                'target_language' => $targetLanguage,
                'source_language' => $sourceLanguage,
                'error' => $e->getMessage()
            ]);

            // 번역 실패 시 원본 텍스트 반환
            return [
                'success' => false,
                'original' => $text,
                'translated' => $text,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 다중 메시지 번역 (배치 처리)
     *
     * @param array $messages [['id' => messageId, 'content' => text, 'sender_language' => lang], ...]
     * @param string $targetLanguage 대상 언어 코드
     * @return array 메시지 ID를 키로 하는 번역 결과 배열
     */
    public function translateMultipleMessages($messages, $targetLanguage)
    {
        if (empty($messages)) {
            return [];
        }

        $messageIds = collect($messages)->pluck('id')->toArray();

        // 기존 번역 조회
        $existingTranslations = ChatMessageTranslation::getTranslationsForMessages($messageIds, $targetLanguage);

        $results = [];
        $untranslatedMessages = [];

        foreach ($messages as $message) {
            $messageId = $message['id'];

            // 기존 번역이 있는 경우
            if ($existingTranslations->has($messageId)) {
                $results[$messageId] = $existingTranslations[$messageId]->toTranslationArray();
                continue;
            }

            // 번역이 필요한 메시지 수집
            $sourceLanguage = $message['sender_language'] ?? 'auto';
            if ($this->needsTranslation($sourceLanguage, $targetLanguage)) {
                $untranslatedMessages[] = $message;
            } else {
                // 번역 불필요
                $results[$messageId] = ChatMessageTranslation::noTranslationArray(
                    $message['content'],
                    $sourceLanguage,
                    $targetLanguage
                );
            }
        }

        // 새로운 번역 수행
        foreach ($untranslatedMessages as $message) {
            $results[$message['id']] = $this->translateChatMessage(
                $message['id'],
                $message['content'],
                $targetLanguage,
                $message['sender_language'] ?? 'auto'
            );
        }

        return $results;
    }

    /**
     * 메시지 번역 (레거시 호환성)
     *
     * @param string $text 번역할 텍스트
     * @param string $targetLanguage 대상 언어 코드
     * @param string|null $sourceLanguage 원본 언어 코드 (null이면 자동 감지)
     * @return array 번역 결과
     */
    public function translateMessage($text, $targetLanguage, $sourceLanguage = null)
    {
        // 빈 텍스트 처리
        if (empty(trim($text))) {
            return [
                'success' => false,
                'original' => $text,
                'translated' => $text,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'error' => 'Empty text'
            ];
        }

        try {
            // 캐시 키 생성
            $cacheKey = $this->generateCacheKey($text, $targetLanguage, $sourceLanguage);

            // 캐시된 번역 결과 확인
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }

            // 원본 언어 감지 (지정되지 않은 경우)
            if (!$sourceLanguage) {
                $detectedLanguage = $this->detectLanguage($text);
                $sourceLanguage = $detectedLanguage ?: 'auto';
            }

            // 번역 필요성 확인
            if (!$this->needsTranslation($sourceLanguage, $targetLanguage)) {
                $result = [
                    'success' => true,
                    'original' => $text,
                    'translated' => $text,
                    'source_language' => $sourceLanguage,
                    'target_language' => $targetLanguage,
                    'needs_translation' => false
                ];

                Cache::put($cacheKey, $result, $this->cacheExpiry);
                return $result;
            }

            // 구글 번역 실행
            $this->translator->setSource($sourceLanguage);
            $this->translator->setTarget($targetLanguage);

            $translatedText = $this->translator->translate($text);

            $result = [
                'success' => true,
                'original' => $text,
                'translated' => $translatedText,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'needs_translation' => true
            ];

            // 결과 캐싱
            Cache::put($cacheKey, $result, $this->cacheExpiry);

            Log::info('메시지 번역 성공', [
                'original' => $text,
                'translated' => $translatedText,
                'from' => $sourceLanguage,
                'to' => $targetLanguage
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('메시지 번역 실패', [
                'text' => $text,
                'target_language' => $targetLanguage,
                'source_language' => $sourceLanguage,
                'error' => $e->getMessage()
            ]);

            // 번역 실패 시 원본 텍스트 반환
            return [
                'success' => false,
                'original' => $text,
                'translated' => $text,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 텍스트 언어 감지
     *
     * @param string $text
     * @return string|null
     */
    public function detectLanguage($text)
    {
        try {
            $this->translator->setSource('auto');
            $this->translator->setTarget('en'); // 임시 타겟 설정

            // 번역을 시도하면서 언어 감지
            $this->translator->translate($text);

            // 감지된 언어 반환
            return $this->translator->getLastDetectedSource();

        } catch (\Exception $e) {
            Log::warning('언어 감지 실패', [
                'text' => $text,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * 번역 필요성 확인
     *
     * @param string $sourceLanguage
     * @param string $targetLanguage
     * @return bool
     */
    public function needsTranslation($sourceLanguage, $targetLanguage)
    {
        // 동일한 언어인 경우 번역 불필요
        if ($sourceLanguage === $targetLanguage) {
            return false;
        }

        // 언어 코드 정규화 (예: ko-KR -> ko)
        $sourceNormalized = $this->normalizeLanguageCode($sourceLanguage);
        $targetNormalized = $this->normalizeLanguageCode($targetLanguage);

        return $sourceNormalized !== $targetNormalized;
    }

    /**
     * 언어 코드 정규화
     *
     * @param string $languageCode
     * @return string
     */
    protected function normalizeLanguageCode($languageCode)
    {
        // 소문자로 변환
        $code = strtolower($languageCode);

        // 지역 코드 제거 (예: ko-KR -> ko)
        if (strpos($code, '-') !== false) {
            $code = explode('-', $code)[0];
        }

        // 특별한 경우 처리
        $mappings = [
            'auto' => 'auto',
            'zh-cn' => 'zh',
            'zh-tw' => 'zh',
        ];

        return $mappings[$code] ?? $code;
    }

    /**
     * 캐시 키 생성
     *
     * @param string $text
     * @param string $targetLanguage
     * @param string|null $sourceLanguage
     * @return string
     */
    protected function generateCacheKey($text, $targetLanguage, $sourceLanguage = null)
    {
        $textHash = md5($text);
        $source = $sourceLanguage ?: 'auto';

        return "{$this->cachePrefix}:{$source}:{$targetLanguage}:{$textHash}";
    }

    /**
     * 특정 메시지의 번역 캐시 삭제
     *
     * @param string $text
     * @param string $targetLanguage
     * @param string|null $sourceLanguage
     * @return bool
     */
    public function clearTranslationCache($text, $targetLanguage, $sourceLanguage = null)
    {
        $cacheKey = $this->generateCacheKey($text, $targetLanguage, $sourceLanguage);
        return Cache::forget($cacheKey);
    }

    /**
     * 모든 번역 캐시 삭제
     *
     * @return bool
     */
    public function clearAllTranslationCache()
    {
        // Laravel의 태그된 캐시 사용 (Redis 등에서 지원)
        if (method_exists(Cache::getStore(), 'tags')) {
            return Cache::tags($this->cachePrefix)->flush();
        }

        // 태그를 지원하지 않는 경우, prefix 패턴으로 삭제 시도
        return Cache::flush(); // 전체 캐시 삭제 (주의: 다른 캐시도 삭제됨)
    }

    /**
     * 지원하는 언어 목록 반환
     *
     * @return array
     */
    public function getSupportedLanguages()
    {
        return [
            'ko' => '한국어',
            'en' => 'English',
            'ja' => '日本語',
            'zh' => '中文',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'ru' => 'Русский',
            'pt' => 'Português',
            'it' => 'Italiano',
            'ar' => 'العربية',
            'hi' => 'हिन्दी',
            'th' => 'ไทย',
            'vi' => 'Tiếng Việt',
            'id' => 'Bahasa Indonesia',
        ];
    }

    /**
     * 번역 통계 정보 반환
     *
     * @return array
     */
    public function getTranslationStats()
    {
        // 실제 구현에서는 별도 테이블에 통계 저장
        return [
            'total_translations' => 0,
            'cached_translations' => 0,
            'failed_translations' => 0,
            'most_translated_languages' => [],
        ];
    }
}