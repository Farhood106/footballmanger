<?php
// app/Services/DailySchedulerService.php

class DailySchedulerService {
    private MatchModel $matchModel;
    private MatchEngine $engine;

    public function __construct(?MatchModel $matchModel = null, ?MatchEngine $engine = null) {
        $this->matchModel = $matchModel ?? new MatchModel();
        $this->engine = $engine ?? new MatchEngine();
    }

    public function runDueMatches(?\DateTimeImmutable $now = null): array {
        $currentTime = $now ?? new \DateTimeImmutable();
        $scheduled = $this->matchModel->getScheduled();

        $results = [
            'executed_at' => $currentTime->format('Y-m-d H:i:s'),
            'simulated' => [],
            'failed' => []
        ];

        foreach ($scheduled as $match) {
            try {
                $results['simulated'][] = $this->engine->simulate((int) $match['id']);
            } catch (\Throwable $e) {
                $results['failed'][] = [
                    'match_id' => (int) $match['id'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
