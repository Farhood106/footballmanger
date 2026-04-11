<?php
// app/Controllers/AdminCompetitionController.php

class AdminCompetitionController extends Controller {
    private AdminCompetitionService $service;

    public function __construct() {
        parent::__construct();
        $this->service = new AdminCompetitionService();
    }

    public function index(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $this->view('admin/competitions', [
            'competitions' => $this->service->listCompetitionsWithSeasons(),
        ]);
    }

    public function createCompetition(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $result = $this->service->createCompetition($_POST);
        $this->renderBack($result);
    }

    public function updateCompetition(int $id): void {
        $this->requireAuth();
        $this->requireAdmin();

        $result = $this->service->updateCompetition($id, $_POST);
        $this->renderBack($result);
    }

    public function toggleCompetition(int $id): void {
        $this->requireAuth();
        $this->requireAdmin();

        $this->service->toggleCompetition($id, ((int)($_POST['is_active'] ?? 0) === 1));
        $this->redirect('/admin/competitions');
    }

    public function createSeason(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $result = $this->service->createSeason(
            (int)($_POST['competition_id'] ?? 0),
            trim((string)($_POST['name'] ?? '')),
            trim((string)($_POST['start_date'] ?? '')),
            trim((string)($_POST['end_date'] ?? '')),
        );

        $this->renderBack($result);
    }

    public function startSeason(int $id): void {
        $this->requireAuth();
        $this->requireAdmin();
        $this->renderBack($this->service->startSeason($id));
    }

    public function endSeason(int $id): void {
        $this->requireAuth();
        $this->requireAdmin();
        $this->renderBack($this->service->endSeason($id));
    }

    public function generateFixtures(int $id): void {
        $this->requireAuth();
        $this->requireAdmin();

        $result = $this->service->generateFixtures($id, ((int)($_POST['regenerate'] ?? 0) === 1));
        $this->renderBack($result);
    }

    public function fixtures(int $id): void {
        $this->requireAuth();
        $this->requireAdmin();

        $fixtures = $this->service->getFixturesBySeason($id);
        $this->view('admin/season-fixtures', ['fixtures' => $fixtures, 'season_id' => $id]);
    }

    private function renderBack(array $result): void {
        $this->view('admin/competitions', [
            'competitions' => $this->service->listCompetitionsWithSeasons(),
            'success' => $result['ok'] ? 'Operation completed.' : null,
            'error' => $result['ok'] ? null : ($result['error'] ?? 'Operation failed.')
        ]);
    }
}
