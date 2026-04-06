<?php
// app/Controllers/CompetitionController.php

class CompetitionController extends Controller {
    private CompetitionModel $competitionModel;
    private SeasonModel $seasonModel;

    public function __construct() {
        parent::__construct();
        $this->competitionModel = new CompetitionModel();
        $this->seasonModel = new SeasonModel();
    }

    public function standings(int $competitionId): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $season = $this->seasonModel->getActive();
        $standings = $this->competitionModel->getStandings($competitionId, $season['id']);
        $topScorers = $this->competitionModel->getTopScorers($competitionId, $season['id']);

        $this->view('competition/standings', [
            'standings' => $standings,
            'top_scorers' => $topScorers,
            'season' => $season
        ]);
    }

    public function fixtures(int $competitionId): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $season = $this->seasonModel->getActive();
        $fixtures = $this->competitionModel->getFixtures($competitionId, $season['id']);

        $this->view('competition/fixtures', [
            'fixtures' => $fixtures,
            'season' => $season
        ]);
    }
}
