<?php
// app/Controllers/ClubFacilitiesController.php

class ClubFacilitiesController extends Controller {
    private Database $db;
    private ClubFacilityService $facilities;
    private YouthAcademyService $youthAcademy;

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->facilities = new ClubFacilityService($this->db);
        $this->youthAcademy = new YouthAcademyService($this->db);
    }

    public function index(): void {
        $this->requireAuth();

        $clubs = Auth::isAdmin()
            ? $this->db->fetchAll("SELECT id, name, owner_user_id FROM clubs ORDER BY name ASC")
            : $this->db->fetchAll("SELECT id, name, owner_user_id FROM clubs WHERE owner_user_id = ? ORDER BY name ASC", [(int)Auth::id()]);

        $selectedClubId = (int)($_GET['club_id'] ?? ($clubs[0]['id'] ?? 0));
        $selectedClub = $selectedClubId > 0
            ? $this->db->fetchOne("SELECT id, name, balance, reputation FROM clubs WHERE id = ?", [$selectedClubId])
            : null;

        $facilityRows = $selectedClubId > 0 ? $this->facilities->getFacilitiesForClub($selectedClubId) : [];
        $latestIntakes = $selectedClubId > 0 ? $this->youthAcademy->getLatestIntakesForClub($selectedClubId, 5) : [];
        $academyPlayers = $selectedClubId > 0 ? $this->youthAcademy->getAcademyPlayersForClub($selectedClubId, 20) : [];

        $this->view('club/facilities', [
            'clubs' => $clubs,
            'selected_club_id' => $selectedClubId,
            'selected_club' => $selectedClub,
            'facilities' => $facilityRows,
            'latest_intakes' => $latestIntakes,
            'academy_players' => $academyPlayers,
            'success' => !empty($_GET['success']) ? $_GET['success'] : null,
            'error' => !empty($_GET['error']) ? $_GET['error'] : null,
        ]);
    }

    public function upgrade(): void {
        $this->requireAuth();
        $clubId = (int)($_POST['club_id'] ?? 0);
        $facilityType = trim((string)($_POST['facility_type'] ?? ''));

        $result = $this->facilities->upgradeFacility($clubId, $facilityType, (int)Auth::id(), Auth::isAdmin());
        $this->redirectResult($clubId, $result);
    }

    public function downgrade(): void {
        $this->requireAuth();
        $clubId = (int)($_POST['club_id'] ?? 0);
        $facilityType = trim((string)($_POST['facility_type'] ?? ''));

        $result = $this->facilities->downgradeFacility($clubId, $facilityType, (int)Auth::id(), Auth::isAdmin());
        $this->redirectResult($clubId, $result);
    }

    private function redirectResult(int $clubId, array $result): void {
        if (!empty($result['ok'])) {
            $this->redirect('/club/facilities?club_id=' . $clubId . '&success=' . urlencode('Facility operation completed'));
        }
        $this->redirect('/club/facilities?club_id=' . $clubId . '&error=' . urlencode((string)($result['error'] ?? 'Operation failed')));
    }
}
