<?php
// app/Controllers/AdminMatchOperationsController.php

class AdminMatchOperationsController extends Controller {
    private Database $db;
    private AdminMatchOperationsService $service;

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->service = new AdminMatchOperationsService();
    }

    public function index(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $filters = [
            'status' => trim((string)($_GET['status'] ?? '')),
            'season_id' => (int)($_GET['season_id'] ?? 0),
            'competition_id' => (int)($_GET['competition_id'] ?? 0),
            'club_id' => (int)($_GET['club_id'] ?? 0),
        ];

        $cycleDate = trim((string)($_GET['cycle_date'] ?? date('Y-m-d')));

        $this->view('admin/match-operations', [
            'filters' => $filters,
            'matches' => $this->service->getMatches($filters),
            'statuses' => ['SCHEDULED', 'LIVE', 'FINISHED'],
            'seasons' => $this->db->fetchAll("SELECT id, name FROM seasons ORDER BY id DESC LIMIT 200"),
            'competitions' => $this->db->fetchAll("SELECT id, name FROM competitions ORDER BY name ASC"),
            'clubs' => $this->db->fetchAll("SELECT id, name FROM clubs ORDER BY name ASC"),
            'cycle_date' => $cycleDate,
            'cycle_states' => $this->service->getCycleStates($cycleDate),
            'success' => !empty($_GET['success']) ? trim((string)$_GET['success']) : null,
            'error' => !empty($_GET['error']) ? trim((string)$_GET['error']) : null,
        ]);
    }

    public function repair(int $matchId): void {
        $this->requireAuth();
        $this->requireAdmin();

        $result = $this->service->repairLiveToScheduled($matchId, (int)Auth::id());
        $this->redirectWithResult($result, 'LIVE repair completed.');
    }

    public function rerun(int $matchId): void {
        $this->requireAuth();
        $this->requireAdmin();

        $override = ((int)($_POST['override'] ?? 0) === 1);
        $result = $this->service->rerunMatch($matchId, (int)Auth::id(), $override);
        $this->redirectWithResult($result, 'Rerun completed.');
    }

    public function resetLineup(int $matchId): void {
        $this->requireAuth();
        $this->requireAdmin();

        $result = $this->service->resetLineupLock($matchId, (int)Auth::id());
        $this->redirectWithResult($result, 'Lineup lock reset completed.');
    }

    public function syncCycle(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $clubId = (int)($_POST['club_id'] ?? 0);
        $cycleDate = trim((string)($_POST['cycle_date'] ?? ''));

        $result = $this->service->syncCycleState($clubId, $cycleDate, (int)Auth::id());
        $this->redirectWithResult($result, 'Cycle state sync completed.', $cycleDate);
    }

    private function redirectWithResult(array $result, string $successMessage, ?string $cycleDate = null): void {
        $query = [];
        if (!empty($result['ok'])) {
            $query['success'] = $successMessage;
        } else {
            $query['error'] = (string)($result['error'] ?? 'Operation failed.');
        }

        if ($cycleDate !== null && $cycleDate !== '') {
            $query['cycle_date'] = $cycleDate;
        }

        $url = '/admin/match-operations';
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $this->redirect($url);
    }
}
