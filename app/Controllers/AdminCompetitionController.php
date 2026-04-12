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
            'clubs' => $this->service->listClubs(),
            'entry_types' => AdminCompetitionService::entryTypes(),
            'qualification_slots' => $this->service->listQualificationSlots(),
            'league_competitions' => $this->service->listLeagueCompetitions(),
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


    public function addParticipant(int $seasonId): void {
        $this->requireAuth();
        $this->requireAdmin();

        $clubId = (int)($_POST['club_id'] ?? 0);
        $entryType = trim((string)($_POST['entry_type'] ?? 'direct'));
        $result = $this->service->addSeasonParticipant($seasonId, $clubId, $entryType);
        $this->renderBack($result);
    }

    public function removeParticipant(int $seasonId, int $clubId): void {
        $this->requireAuth();
        $this->requireAdmin();

        $result = $this->service->removeSeasonParticipant($seasonId, $clubId);
        $this->renderBack($result);
    }


    public function finalizeSeason(int $id): void {
        $this->requireAuth();
        $this->requireAdmin();
        $this->renderBack($this->service->finalizeSeason($id));
    }

    public function applyRollover(int $id): void {
        $this->requireAuth();
        $this->requireAdmin();
        $result = $this->service->applyRollover($id, true);
        $this->renderBack($result);
    }

    public function fixtures(int $id): void {
        $this->requireAuth();
        $this->requireAdmin();

        $fixtures = $this->service->getFixturesBySeason($id);
        $this->view('admin/season-fixtures', ['fixtures' => $fixtures, 'season_id' => $id]);
    }

    public function saveQualificationSlot(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $result = $this->service->saveQualificationSlot(
            (int)($_POST['source_competition_id'] ?? 0),
            (int)($_POST['target_competition_id'] ?? 0),
            (int)($_POST['slots'] ?? 0),
            !empty($_POST['is_active'])
        );
        $this->renderBack($result);
    }

    public function previewQualifications(int $targetSeasonId): void {
        $this->requireAuth();
        $this->requireAdmin();
        $result = $this->service->previewChampionsQualification($targetSeasonId);
        $this->renderBack($result, ['qualification_preview' => $result]);
    }

    public function applyQualifications(int $targetSeasonId): void {
        $this->requireAuth();
        $this->requireAdmin();
        $result = $this->service->applyChampionsQualification($targetSeasonId);
        $this->renderBack($result, ['qualification_preview' => $this->service->previewChampionsQualification($targetSeasonId)]);
    }

    private function renderBack(array $result, array $extra = []): void {
        $this->view('admin/competitions', [
            'competitions' => $this->service->listCompetitionsWithSeasons(),
            'clubs' => $this->service->listClubs(),
            'entry_types' => AdminCompetitionService::entryTypes(),
            'qualification_slots' => $this->service->listQualificationSlots(),
            'league_competitions' => $this->service->listLeagueCompetitions(),
            'success' => $result['ok'] ? 'Operation completed.' : null,
            'error' => $result['ok'] ? null : ($result['error'] ?? 'Operation failed.')
        ] + $extra);
    }
}
