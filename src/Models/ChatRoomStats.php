<?php

namespace Jiny\Chat\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ChatRoomStats - 채팅방별 독립 통계 모델
 */
class ChatRoomStats extends ChatRoomModel
{
    use HasFactory;

    protected $table = 'chat_room_stats';

    protected $fillable = [
        'date',
        'message_count',
        'participant_count',
        'file_count',
        'file_size_total',
        'hourly_stats',
        'user_activity',
    ];

    protected $casts = [
        'date' => 'date',
        'hourly_stats' => 'array',
        'user_activity' => 'array',
    ];

    /**
     * 일별 통계 업데이트
     */
    public static function updateDailyStats($roomCode, $data)
    {
        $today = now()->toDateString();

        $stats = static::forRoom($roomCode)->whereDate('date', $today)->first();

        if ($stats) {
            $stats->update($data);
        } else {
            $defaultData = [
                'date' => $today,
                'message_count' => 0,
                'participant_count' => 0,
                'file_count' => 0,
                'file_size_total' => 0,
                'hourly_stats' => array_fill(0, 24, 0),
                'user_activity' => [],
            ];

            static::forRoom($roomCode)->create(array_merge($defaultData, $data));
        }
    }

    /**
     * 시간별 메시지 수 증가
     */
    public static function incrementHourlyMessage($roomCode, $hour = null)
    {
        if ($hour === null) {
            $hour = now()->hour;
        }

        $today = now()->toDateString();
        $stats = static::forRoom($roomCode)->whereDate('date', $today)->first();

        if ($stats) {
            $hourlyStats = $stats->hourly_stats ?? array_fill(0, 24, 0);
            $hourlyStats[$hour] = ($hourlyStats[$hour] ?? 0) + 1;

            $stats->update(['hourly_stats' => $hourlyStats]);
        }
    }

    /**
     * 사용자 활동 업데이트
     */
    public static function updateUserActivity($roomCode, $userUuid, $activityType = 'message')
    {
        $today = now()->toDateString();
        $stats = static::forRoom($roomCode)->whereDate('date', $today)->first();

        if ($stats) {
            $userActivity = $stats->user_activity ?? [];
            $userActivity[$userUuid] = $userActivity[$userUuid] ?? [];
            $userActivity[$userUuid][$activityType] = ($userActivity[$userUuid][$activityType] ?? 0) + 1;
            $userActivity[$userUuid]['last_activity'] = now()->toISOString();

            $stats->update(['user_activity' => $userActivity]);
        }
    }

    /**
     * 기간별 통계 조회
     */
    public static function getStatsForPeriod($roomCode, $startDate, $endDate)
    {
        return static::forRoom($roomCode)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();
    }

    /**
     * 월별 요약 통계
     */
    public static function getMonthlySummary($roomCode, $year, $month)
    {
        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $stats = static::getStatsForPeriod($roomCode, $startDate, $endDate);

        return [
            'total_messages' => $stats->sum('message_count'),
            'total_files' => $stats->sum('file_count'),
            'total_file_size' => $stats->sum('file_size_total'),
            'avg_participants' => $stats->avg('participant_count'),
            'peak_participants' => $stats->max('participant_count'),
            'active_days' => $stats->where('message_count', '>', 0)->count(),
            'daily_stats' => $stats,
        ];
    }

    /**
     * 가장 활발한 시간대 분석
     */
    public static function getPeakHours($roomCode, $days = 7)
    {
        $startDate = now()->subDays($days)->toDateString();
        $endDate = now()->toDateString();

        $stats = static::getStatsForPeriod($roomCode, $startDate, $endDate);

        $totalHourlyStats = array_fill(0, 24, 0);

        foreach ($stats as $stat) {
            $hourlyStats = $stat->hourly_stats ?? array_fill(0, 24, 0);
            for ($i = 0; $i < 24; $i++) {
                $totalHourlyStats[$i] += $hourlyStats[$i] ?? 0;
            }
        }

        // 가장 활발한 시간대 찾기
        $peakHour = array_search(max($totalHourlyStats), $totalHourlyStats);

        return [
            'hourly_distribution' => $totalHourlyStats,
            'peak_hour' => $peakHour,
            'peak_messages' => max($totalHourlyStats),
            'total_messages' => array_sum($totalHourlyStats),
        ];
    }

    /**
     * 활발한 사용자 분석
     */
    public static function getActiveUsers($roomCode, $days = 7)
    {
        $startDate = now()->subDays($days)->toDateString();
        $endDate = now()->toDateString();

        $stats = static::getStatsForPeriod($roomCode, $startDate, $endDate);

        $userActivity = [];

        foreach ($stats as $stat) {
            $dailyActivity = $stat->user_activity ?? [];
            foreach ($dailyActivity as $userUuid => $activity) {
                if (!isset($userActivity[$userUuid])) {
                    $userActivity[$userUuid] = [
                        'message' => 0,
                        'file' => 0,
                        'last_activity' => null,
                    ];
                }

                $userActivity[$userUuid]['message'] += $activity['message'] ?? 0;
                $userActivity[$userUuid]['file'] += $activity['file'] ?? 0;

                if (isset($activity['last_activity'])) {
                    if (!$userActivity[$userUuid]['last_activity'] ||
                        $activity['last_activity'] > $userActivity[$userUuid]['last_activity']) {
                        $userActivity[$userUuid]['last_activity'] = $activity['last_activity'];
                    }
                }
            }
        }

        // 메시지 수로 정렬
        uasort($userActivity, function ($a, $b) {
            return $b['message'] <=> $a['message'];
        });

        return $userActivity;
    }
}