<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ChatRoomMessageTranslation - 채팅방별 독립 메시지 번역 모델
 */
class ChatRoomMessageTranslation extends ChatRoomModel
{
    use HasFactory;

    protected $table = 'chat_message_translations';

    protected $fillable = [
        'message_id',
        'language_code',
        'original_content',
        'translated_content',
        'encrypted_translated_content',
        'translation_service',
        'translation_metadata',
    ];

    protected $casts = [
        'translation_metadata' => 'array',
    ];

    /**
     * 메시지 관계
     */
    public function message()
    {
        return $this->belongsTo(ChatRoomMessage::class, 'message_id');
    }

    /**
     * 메시지 번역 생성/업데이트
     */
    public static function translateMessage($roomCode, $messageId, $languageCode, $translatedContent, $options = [])
    {
        $message = ChatRoomMessage::forRoom($roomCode)->find($messageId);
        if (!$message) {
            throw new \Exception('Message not found');
        }

        $data = [
            'message_id' => $messageId,
            'language_code' => $languageCode,
            'original_content' => $message->content,
            'translated_content' => $translatedContent,
            'translation_service' => $options['service'] ?? 'google',
            'translation_metadata' => $options['metadata'] ?? [],
        ];

        // 암호화가 필요한 경우
        if (isset($options['encryption_key'])) {
            $data['encrypted_translated_content'] = $message->encryptMessage($translatedContent, $options['encryption_key']);
        }

        return static::forRoom($roomCode)->updateOrCreate(
            ['message_id' => $messageId, 'language_code' => $languageCode],
            $data
        );
    }

    /**
     * 구글 번역 API를 사용한 자동 번역
     */
    public static function autoTranslate($roomCode, $messageId, $targetLanguage, $sourceLanguage = null)
    {
        $message = ChatRoomMessage::forRoom($roomCode)->find($messageId);
        if (!$message || !$message->content) {
            return false;
        }

        try {
            // Google Translate 라이브러리 사용
            if (class_exists('\Stichoza\GoogleTranslate\GoogleTranslate')) {
                $translator = new \Stichoza\GoogleTranslate\GoogleTranslate($sourceLanguage);
                $translator->setOptions(['verify' => false]);
                $translatedText = $translator->setTarget($targetLanguage)->translate($message->content);

                return static::translateMessage($roomCode, $messageId, $targetLanguage, $translatedText, [
                    'service' => 'google',
                    'metadata' => [
                        'source_language' => $sourceLanguage,
                        'target_language' => $targetLanguage,
                        'auto_translated' => true,
                        'translated_at' => now()->toISOString(),
                    ]
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Translation failed: ' . $e->getMessage());
            return false;
        }

        return false;
    }

    /**
     * 여러 언어로 동시 번역
     */
    public static function translateToMultipleLanguages($roomCode, $messageId, array $targetLanguages, $sourceLanguage = null)
    {
        $results = [];

        foreach ($targetLanguages as $lang) {
            $result = static::autoTranslate($roomCode, $messageId, $lang, $sourceLanguage);
            if ($result) {
                $results[$lang] = $result;
            }
        }

        return $results;
    }

    /**
     * 번역 품질 평가 및 개선
     */
    public function improveTranslation($newTranslation, $feedback = null)
    {
        $metadata = $this->translation_metadata ?? [];
        $metadata['improvements'] = $metadata['improvements'] ?? [];
        $metadata['improvements'][] = [
            'previous_translation' => $this->translated_content,
            'new_translation' => $newTranslation,
            'feedback' => $feedback,
            'improved_at' => now()->toISOString(),
        ];

        $this->update([
            'translated_content' => $newTranslation,
            'translation_metadata' => $metadata,
        ]);

        return $this;
    }
}