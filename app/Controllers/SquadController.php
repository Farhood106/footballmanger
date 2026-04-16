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
        $phaseKey = (string)($_GET['phase_key'] ?? 'MATCH_1');
        $selectedFormation = (string)($_GET['formation'] ?? ($activeTactic['formation'] ?? $this->tacticModel->getDefaultFormation()));
        if (!isset($formations[$selectedFormation])) {
            $selectedFormation = $this->tacticModel->getDefaultFormation();
        }

        $existingByKey = [];
        foreach (($activeTactic['lineups'][$phaseKey] ?? []) as $row) {
            $slotKey = ((string)($row['position_slot'] ?? 'CM')) . '__' . max(1, (int)($row['slot_order'] ?? 1));
            $existingByKey[$slotKey] = (int)($row['player_id'] ?? 0);
        }
        $formationSlots = $this->tacticModel->getFormationSlots($selectedFormation);
        $lineupBoard = $this->tacticModel->buildLineupSelectionData($squad, $formationSlots, $existingByKey);

        $this->view('squad/tactics', [
            'club' => $club,
            'tactic' => $activeTactic,
            'squad' => $squad,
            'formations' => $formations,
            'selected_formation' => $selectedFormation,
            'phase_key' => $phaseKey,
            'lineup_board' => $lineupBoard,
        ]);
    }

    public function saveTactic(): void {
        if (!Auth::check()) {
            $this->json(['error' => 'Unauthorized'], 401);
        }

        $clubId = $this->getClubId();
        $formation = (string)($_POST['formation'] ?? '');
        $mentality = $_POST['mentality'] ?? 'BALANCED';
        $phaseKey = (string)($_POST['phase_key'] ?? 'MATCH_1');
        $lineupInput = $_POST['lineup'] ?? [];
        if (!is_array($lineupInput)) {
            $lineupInput = [];
        }
        $formations = $this->tacticModel->getValidFormations();
        if (!isset($formations[$formation])) {
            $this->json(['error' => 'فرمیشن نامعتبر است'], 400);
        }

        $slots = $this->tacticModel->getFormationSlots($formation);
        $lineupRows = [];
        $seenPlayers = [];
        foreach ($slots as $slot) {
            $slotKey = (string)$slot['slot_key'];
            $playerId = (int)($lineupInput[$slotKey] ?? 0);
            if ($playerId <= 0) {
                $this->json(['error' => 'برای همه اسلات‌ها بازیکن انتخاب کنید'], 400);
            }
            if (isset($seenPlayers[$playerId])) {
                $this->json(['error' => 'یک بازیکن نمی‌تواند در چند اسلات همزمان قرار گیرد'], 400);
            }
            $seenPlayers[$playerId] = true;
            $lineupRows[] = [
                'position_slot' => (string)$slot['position_slot'],
                'slot_order' => (int)$slot['slot_order'],
                'player_id' => $playerId,
            ];
        }

        if (count($lineupRows) < 11) {
            $this->json(['error' => 'ترکیب باید حداقل ۱۱ بازیکن داشته باشد'], 400);
        }

        $this->tacticModel->saveTacticalSetup($clubId, [
            'formation' => $formation,
            'mentality' => $mentality
        ]);

        $this->tacticModel->saveLineup($clubId, $phaseKey, $lineupRows);
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
