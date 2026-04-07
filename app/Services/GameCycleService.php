<?php
// app/Services/GameCycleService.php

class GameCycleService {
    private array $config;

    public function __construct(?array $config = null) {
        $allConfig = $config ?? require __DIR__ . '/../../config/config.php';
        $this->config = $allConfig['game'];
    }

    public function getCyclePhases(bool $hasSecondMatch): array {
        $phases = $this->config['daily_cycle']['phases'];

        if ($hasSecondMatch) {
            return $phases['two_matches'];
        }

        return $phases['one_match'];
    }

    public function getCurrentPhase(
        \DateTimeImmutable $now,
        bool $hasSecondMatch,
        ?\DateTimeZone $timezone = null
    ): array {
        $tz = $timezone ?? new \DateTimeZone($this->config['timezone'] ?? 'UTC');
        $localNow = $now->setTimezone($tz);
        $time = $localNow->format('H:i');

        $phases = $this->getCyclePhases($hasSecondMatch);

        foreach ($phases as $phase) {
            if ($this->isTimeInRange($time, $phase['start'], $phase['end'])) {
                return [
                    'phase_key' => $phase['key'],
                    'label' => $phase['label'],
                    'start' => $phase['start'],
                    'end' => $phase['end'],
                    'is_locked' => $phase['is_locked'],
                    'local_time' => $localNow->format('Y-m-d H:i:s')
                ];
            }
        }

        return [
            'phase_key' => 'OFF_HOURS',
            'label' => 'ساعت‌های خارج از چرخه روزانه',
            'start' => null,
            'end' => null,
            'is_locked' => true,
            'local_time' => $localNow->format('Y-m-d H:i:s')
        ];
    }

    public function getTimeline(bool $hasSecondMatch): array {
        $phases = $this->getCyclePhases($hasSecondMatch);

        return array_map(function ($phase) {
            return [
                'phase_key' => $phase['key'],
                'label' => $phase['label'],
                'time_window' => $phase['start'] . ' - ' . $phase['end'],
                'is_locked' => $phase['is_locked'],
                'actions' => $phase['actions'],
            ];
        }, $phases);
    }

    private function isTimeInRange(string $time, string $start, string $end): bool {
        return $time >= $start && $time <= $end;
    }
}
