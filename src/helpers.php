<?php

/**
 * Chat Helper Functions
 *
 * 채팅 관련 전역 헬퍼 함수들
 */

if (!function_exists('getAvatarText')) {
    /**
     * 사용자 이름에서 아바타 텍스트 추출
     *
     * @param string $name 사용자 이름
     * @return string 아바타 텍스트 (첫 글자)
     */
    function getAvatarText($name)
    {
        // 이름이 비어있으면 기본값 반환
        $name = trim((string) $name);
        if (empty($name)) {
            return 'U';
        }

        // UTF-8 안전한 첫 글자 추출 (한글, 영문 등 지원)
        $firstChar = mb_substr($name, 0, 1, 'UTF-8');
        return $firstChar ?: 'U';
    }
}