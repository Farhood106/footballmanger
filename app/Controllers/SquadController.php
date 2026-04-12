<?php
// app/Controllers/SquadController.php

class SquadController extends Controller {
    private ClubModel $clubModel;
    private PlayerModel $playerModel;
    private TacticModel $tacticModel;

    public function __construct() {
        parent::__construct();
        $this->clubModel = new ClubModel();
        $this->playerModel = new PlayerModel();
        $this->tacticModel = new TacticModel();
    }

    public function index(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $club = $this->clubModel->find($this->getClubId());
        $squad = $this->clubModel->getSquad($club['id']);
        $injured = $this->playerModel->getInjured($club['id']);

        $this->view('squad/index', [
            'club' => $club,
            'squad' => $squad,
            'injured' => $injured
        ]);
    }

    public function tactics(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $club = $this->clubModel->find($this->getClubId());
        $activeTactic = $this->tacticModel->getActiveByClub($club['id']);
        $squad = $this->clubModel->getSquad($club['id']);
        $formations = $this->tacticModel->getValidFormations();

        $this->view('squad/tactics', [
            'club' => $club,
            'tactic' => $activeTactic,
            'squad' => $squad,
            'formations' => $formations
        ]);
    }

    public function saveTactic(): void {
        if (!Auth::check()) {
            $this->json(['error' => 'Unauthorized'], 401);
        }

        $clubId = $this->getClubId();
        $formation = $_POST['formation'] ?? '';
        $mentality = $_POST['mentality'] ?? 'BALANCED';
        $lineup = json_decode($_POST['lineup'] ?? '[]', true);

        if (!$formation || empty($lineup)) {
            $this->json(['error' => 'فرمیشن و ترکیب الزامی است'], 400);
        }

        $this->tacticModel->saveTacticalSetup($clubId, [
            'formation' => $formation,
            'mentality' => $mentality
        ]);

        $phaseKey = $_POST['phase_key'] ?? 'MATCH_1';
        $this->tacticModel->saveLineup($clubId, $phaseKey, $lineup);
        $this->json(['success' => true, 'message' => 'تاکتیک با موفقیت ذخیره شد']);
    }

    public function playerDetail(int $playerId): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $player = $this->playerModel->getWithAbilities($playerId);
        if (!$player || $player['club_id'] != $this->getClubId()) {
            $this->redirect('/squad');
        }

        $seasonStats = $this->playerModel->getSeasonStats($playerId, $this->getCurrentSeasonId());
        $careerStats = $this->playerModel->getCareerStats($playerId);

        $this->view('squad/player-detail', [
            'player' => $player,
            'season_stats' => $seasonStats,
            'career_stats' => $careerStats
        ]);
    }

    private function getClubId(): int {
        $club = (new UserModel())->getClub(Auth::id());
        return $club['id'] ?? 0;
    }

    private function getCurrentSeasonId(): int {
        $season = (new SeasonModel())->getActive();
        return $season['id'] ?? 0;
    }
}
