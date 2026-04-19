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

        $club = $this->requireClubForPage();
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

        $club = $this->requireClubForPage();
        $activeTactic = $this->tacticModel->getActiveByClub($club['id']);
        if (!$activeTactic) {
            $this->tacticModel->saveTacticalSetup((int)$club['id'], [
                'formation' => $this->tacticModel->getDefaultFormation(),
                'mentality' => $this->tacticModel->getDefaultMentality(),
                'captain' => null,
                'penalty_taker' => null,
                'freekick_taker' => null,
                'corner_taker' => null,
            ]);
            $activeTactic = $this->tacticModel->getActiveByClub($club['id']) ?? [];
        }
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
        $activeTactic = $this->tacticModel->normalizeResponsibilitiesForLineup($activeTactic, $lineupBoard);
        $activeTactic['mentality'] = $this->tacticModel->normalizeMentality((string)($activeTactic['mentality'] ?? ''));
        $responsibilityRankings = $this->tacticModel->buildResponsibilityRankings($squad, $lineupBoard);
        $mentalities = $this->tacticModel->getValidMentalities();

        $this->view('squad/tactics', [
            'club' => $club,
            'tactic' => $activeTactic,
            'squad' => $squad,
            'formations' => $formations,
            'mentalities' => $mentalities,
            'selected_formation' => $selectedFormation,
            'phase_key' => $phaseKey,
            'lineup_board' => $lineupBoard,
            'responsibility_rankings' => $responsibilityRankings,
        ]);
    }

    public function saveTactic(): void {
        if (!Auth::check()) {
            $this->json(['error' => 'Unauthorized'], 401);
        }

        $clubId = $this->requireClubIdForJson();
        $formation = (string)($_POST['formation'] ?? '');
        $mentality = $this->tacticModel->normalizeMentality((string)($_POST['mentality'] ?? $this->tacticModel->getDefaultMentality()));
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
        $lineupPlayerIds = [];
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
            $lineupPlayerIds[$playerId] = true;
            $lineupRows[] = [
                'position_slot' => (string)$slot['position_slot'],
                'slot_order' => (int)$slot['slot_order'],
                'player_id' => $playerId,
            ];
        }

        if (count($lineupRows) < 11) {
            $this->json(['error' => 'ترکیب باید حداقل ۱۱ بازیکن داشته باشد'], 400);
        }

        $captain = (int)($_POST['captain'] ?? 0);
        $penaltyTaker = (int)($_POST['penalty_taker'] ?? 0);
        $freekickTaker = (int)($_POST['freekick_taker'] ?? 0);
        $cornerTaker = (int)($_POST['corner_taker'] ?? 0);
        $squadIds = array_flip(array_map(fn($p) => (int)($p['id'] ?? 0), $this->clubModel->getSquad($clubId)));
        foreach ([$captain, $penaltyTaker, $freekickTaker, $cornerTaker] as $rolePlayerId) {
            if ($rolePlayerId > 0 && !isset($squadIds[$rolePlayerId])) {
                $this->json(['error' => 'مسئول انتخاب‌شده خارج از اسکواد باشگاه است'], 400);
            }
        }

        $normalizedRoles = [
            'captain' => ($captain > 0 && isset($lineupPlayerIds[$captain])) ? $captain : null,
            'penalty_taker' => ($penaltyTaker > 0 && isset($lineupPlayerIds[$penaltyTaker])) ? $penaltyTaker : null,
            'freekick_taker' => ($freekickTaker > 0 && isset($lineupPlayerIds[$freekickTaker])) ? $freekickTaker : null,
            'corner_taker' => ($cornerTaker > 0 && isset($lineupPlayerIds[$cornerTaker])) ? $cornerTaker : null,
        ];
        $clearedRoles = [];
        if ($captain > 0 && $normalizedRoles['captain'] === null) $clearedRoles[] = 'کاپیتان';
        if ($penaltyTaker > 0 && $normalizedRoles['penalty_taker'] === null) $clearedRoles[] = 'پنالتی';
        if ($freekickTaker > 0 && $normalizedRoles['freekick_taker'] === null) $clearedRoles[] = 'ضربه آزاد';
        if ($cornerTaker > 0 && $normalizedRoles['corner_taker'] === null) $clearedRoles[] = 'کرنر';

        $this->tacticModel->saveSetupAndLineup($clubId, [
            'formation' => $formation,
            'mentality' => $mentality,
            'captain' => $normalizedRoles['captain'],
            'penalty_taker' => $normalizedRoles['penalty_taker'],
            'freekick_taker' => $normalizedRoles['freekick_taker'],
            'corner_taker' => $normalizedRoles['corner_taker'],
        ], $phaseKey, $lineupRows);

        $message = 'تاکتیک با موفقیت ذخیره شد';
        if (!empty($clearedRoles)) {
            $message .= ' (مسئولیت‌های خارج از ترکیب اصلی حذف شدند: ' . implode('، ', $clearedRoles) . ')';
        }

        $this->json(['success' => true, 'message' => $message, 'reload' => true]);
    }

    public function playerDetail(int $playerId): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $clubId = $this->getClubId();
        if ($clubId <= 0) {
            $this->redirect('/dashboard?error=' . urlencode('هیچ باشگاهی برای حساب شما تنظیم نشده است.'));
        }

        $player = $this->playerModel->getWithAbilities($playerId);
        if (!$player || $player['club_id'] != $clubId) {
            $this->redirect('/squad?error=' . urlencode('بازیکن برای باشگاه فعلی در دسترس نیست.'));
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

        $clubId = $this->requireClubIdForJson();
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
        $club = $this->resolveCurrentClub();
        return $club['id'] ?? 0;
    }

    private function resolveCurrentClub(): ?array {
        $requestedClubId = (int)($_GET['club_id'] ?? $_POST['club_id'] ?? 0);
        if ($requestedClubId > 0 && Auth::isAdmin()) {
            $requestedClub = $this->clubModel->find($requestedClubId);
            if ($requestedClub) {
                return $requestedClub;
            }
        }

        return (new UserModel())->getClub(Auth::id());
    }

    private function requireClubForPage(): array {
        $club = $this->resolveCurrentClub();
        if (!$club) {
            $this->redirect('/dashboard?error=' . urlencode('هیچ باشگاهی برای حساب شما تنظیم نشده است.'));
        }

        return $club;
    }

    private function requireClubIdForJson(): int {
        $clubId = $this->getClubId();
        if ($clubId <= 0) {
            $this->json(['error' => 'هیچ باشگاهی برای حساب شما تنظیم نشده است.'], 400);
        }

        return $clubId;
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
