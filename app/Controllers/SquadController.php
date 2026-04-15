<?php
// app/Controllers/SquadController.php

class SquadController extends Controller {
    private ClubModel $clubModel;
    private PlayerModel $playerModel;
    private TacticModel $tacticModel;
    private YouthIntakeService $youthIntake;

    public function __construct() {
        parent::__construct();
        $this->clubModel = new ClubModel();
        $this->playerModel = new PlayerModel();
        $this->tacticModel = new TacticModel();
        $this->youthIntake = new YouthIntakeService();
    }

    public function index(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $club = $this->clubModel->find($this->getClubId());
        $squad = $this->clubModel->getSquad($club['id']);
        $injured = $this->playerModel->getInjured($club['id']);
        $roleLabels = $this->playerModel->getSquadRoleLabels();
        $recentYouthIntakes = $this->youthIntake->getRecentClubIntakeLogs((int)$club['id'], 8);

        $squad = array_map(function (array $player) use ($roleLabels): array {
            $fullName = trim((string)($player['full_name'] ?? (($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''))));
            $role = strtoupper((string)($player['squad_role'] ?? 'ROTATION'));
            $minutes = (int)($player['last_minutes_played'] ?? 0);
            $lastPlayedAt = $player['last_played_at'] ?? null;
            $daysSince = null;
            if (!empty($lastPlayedAt)) {
                $daysSince = max(0, (int)floor((time() - strtotime((string)$lastPlayedAt)) / 86400));
            }

            $inactivityWarning = $daysSince !== null && $daysSince >= 7;
            $overusedWarning = $minutes >= 85;
            return array_merge($player, [
                'display_name' => $fullName !== '' ? $fullName : ('Player #' . (int)$player['id']),
                'role_label' => $roleLabels[$role] ?? $roleLabels['ROTATION'],
                'recent_minutes' => $minutes,
                'days_since_played' => $daysSince,
                'inactivity_warning' => $inactivityWarning,
                'overused_warning' => $overusedWarning,
                'academy_origin' => !empty($player['is_academy_origin']),
                'academy_origin_club_id' => $player['academy_origin_club_id'] ?? null,
                'academy_intake_season_id' => $player['academy_intake_season_id'] ?? null,
            ]);
        }, $squad);

        $this->view('squad/index', [
            'club' => $club,
            'squad' => $squad,
            'injured' => $injured,
            'role_labels' => $roleLabels,
            'youth_intakes' => $recentYouthIntakes,
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
            'career_stats' => $careerStats,
            'role_labels' => $this->playerModel->getSquadRoleLabels(),
        ]);
    }

    public function saveSquadRole(): void {
        if (!Auth::check()) {
            $this->json(['error' => 'Unauthorized'], 401);
        }

        $clubId = $this->getClubId();
        $playerId = (int)($_POST['player_id'] ?? 0);
        $role = (string)($_POST['squad_role'] ?? '');
        if ($playerId <= 0 || $role === '') {
            $this->json(['error' => 'Invalid request'], 400);
        }

        $ok = $this->playerModel->setSquadRoleForClub($playerId, $clubId, $role);
        if (!$ok) {
            if ($this->wantsJson()) {
                $this->json(['error' => 'Could not update squad role'], 422);
            }
            $this->redirect('/squad');
        }
        if ($this->wantsJson()) {
            $this->json(['ok' => true, 'message' => 'Squad role updated']);
        }
        $this->redirect('/squad');
    }

    private function getClubId(): int {
        $club = (new UserModel())->getClub(Auth::id());
        return $club['id'] ?? 0;
    }

    private function getCurrentSeasonId(): int {
        $season = (new SeasonModel())->getActive();
        return $season['id'] ?? 0;
    }

    private function wantsJson(): bool {
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }
}
