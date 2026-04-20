<?php
// app/Controllers/CompetitionController.php

class CompetitionController extends Controller {
    private CompetitionModel $competitionModel;
    private SeasonModel $seasonModel;
    private UserModel $userModel;

    public function __construct() {
        parent::__construct();
        $this->competitionModel = new CompetitionModel();
        $this->seasonModel = new SeasonModel();
        $this->userModel = new UserModel();
    }

    public function standings(int $competitionId): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $season = $this->seasonModel->getActive();
        if (!$season) {
            $this->view('competition/standings', [
                'standings' => [],
                'top_scorers' => [],
                'season' => null,
                'competition' => $this->competitionModel->find($competitionId) ?: ['id' => $competitionId, 'name' => 'Competition'],
                'userClubId' => 0,
            ]);
            return;
        }

        $standings = $this->competitionModel->getStandings($competitionId, $season['id']);
        $topScorers = $this->competitionModel->getTopScorers($competitionId, $season['id']);
        $club = $this->userModel->getClub((int)Auth::id());
        $competition = $this->competitionModel->find($competitionId) ?: ['id' => $competitionId, 'name' => 'Competition'];

        $this->view('competition/standings', [
            'standings' => $standings,
            'top_scorers' => $topScorers,
            'season' => $season,
            'competition' => $competition,
            'userClubId' => (int)($club['id'] ?? 0),
        ]);
    }

    public function fixtures(int $competitionId): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $season = $this->seasonModel->getActive();
        if (!$season) {
            $this->view('competition/fixtures', [
                'fixtures' => [],
                'season' => null,
                'competition' => $this->competitionModel->find($competitionId) ?: ['id' => $competitionId, 'name' => 'Competition'],
                'userClubId' => 0,
            ]);
            return;
        }

        $rawFixtures = $this->competitionModel->getFixtures($competitionId, $season['id']);
        $fixtures = array_map(static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'matchday' => (int)($row['week'] ?? 0),
                'home_club_id' => (int)$row['home_club_id'],
                'away_club_id' => (int)$row['away_club_id'],
                'home_club_name' => (string)$row['home_club_name'],
                'away_club_name' => (string)$row['away_club_name'],
                'status' => (string)$row['status'],
                'home_goals' => $row['home_score'],
                'away_goals' => $row['away_score'],
                'match_date' => date('Y-m-d H:i', strtotime((string)$row['scheduled_at'])),
            ];
        }, $rawFixtures);
        $club = $this->userModel->getClub((int)Auth::id());
        $competition = $this->competitionModel->find($competitionId) ?: ['id' => $competitionId, 'name' => 'Competition'];

        $this->view('competition/fixtures', [
            'fixtures' => $fixtures,
            'season' => $season,
            'competition' => $competition,
            'userClubId' => (int)($club['id'] ?? 0),
        ]);
    }
}
