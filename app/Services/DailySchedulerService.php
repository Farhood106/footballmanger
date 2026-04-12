<?php
// app/Services/DailySchedulerService.php

class DailySchedulerService {
    private DailyCycleOrchestrator $orchestrator;

    public function __construct(?DailyCycleOrchestrator $orchestrator = null) {
        $this->orchestrator = $orchestrator ?? new DailyCycleOrchestrator();
    }

    public function runDueMatches(?\DateTimeImmutable $now = null): array {
        return $this->orchestrator->run($now);
    }
}
