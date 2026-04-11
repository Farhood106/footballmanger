<?php
// app/Controllers/FinanceController.php

class FinanceController extends Controller {
    private Database $db;
    private FinanceService $finance;

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->finance = new FinanceService($this->db);
    }

    public function index(): void {
        $this->requireAuth();

        $clubs = Auth::isAdmin()
            ? $this->db->fetchAll("SELECT id, name, owner_user_id FROM clubs ORDER BY name ASC")
            : $this->db->fetchAll("SELECT id, name, owner_user_id FROM clubs WHERE owner_user_id = ? ORDER BY name ASC", [(int)Auth::id()]);

        $selectedClubId = (int)($_GET['club_id'] ?? ($clubs[0]['id'] ?? 0));
        $ledger = $selectedClubId > 0 ? $this->finance->getLedgerByClub($selectedClubId) : [];
        $sponsors = $selectedClubId > 0
            ? $this->db->fetchAll("SELECT * FROM club_sponsors WHERE club_id = ? ORDER BY is_active DESC, id DESC", [$selectedClubId])
            : [];

        $this->view('finance/index', [
            'clubs' => $clubs,
            'selected_club_id' => $selectedClubId,
            'ledger' => $ledger,
            'sponsors' => $sponsors,
            'success' => !empty($_GET['success']) ? $_GET['success'] : null,
            'error' => !empty($_GET['error']) ? $_GET['error'] : null,
        ]);
    }

    public function ownerFunding(): void {
        $this->requireAuth();

        $clubId = (int)($_POST['club_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));
        $result = $this->finance->postOwnerFunding($clubId, (int)Auth::id(), $amount, $note, trim((string)($_POST['external_reference'] ?? '')));
        $this->redirectResult($clubId, $result);
    }

    public function addSponsor(): void {
        $this->requireAuth();

        $clubId = (int)($_POST['club_id'] ?? 0);
        if (!$this->canManageClub($clubId)) {
            $this->redirect('/finance?error=Unauthorized');
        }

        $this->db->insert('club_sponsors', [
            'club_id' => $clubId,
            'tier' => trim((string)($_POST['tier'] ?? 'minor')),
            'brand_name' => trim((string)($_POST['brand_name'] ?? 'Unknown Sponsor')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'contact_link' => trim((string)($_POST['contact_link'] ?? '')) ?: null,
            'banner_url' => trim((string)($_POST['banner_url'] ?? '')) ?: null,
            'is_active' => 1,
        ]);

        $this->redirect('/finance?club_id=' . $clubId . '&success=Sponsor added');
    }

    public function sponsorIncome(): void {
        $this->requireAuth();
        $clubId = (int)($_POST['club_id'] ?? 0);
        if (!$this->canManageClub($clubId)) {
            $this->redirect('/finance?error=Unauthorized');
        }

        $result = $this->finance->postSponsorIncome(
            $clubId,
            (int)($_POST['sponsor_id'] ?? 0),
            (int)($_POST['amount'] ?? 0),
            trim((string)($_POST['note'] ?? ''))
        );
        $this->redirectResult($clubId, $result);
    }

    public function manualAdjust(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $clubId = (int)($_POST['club_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $note = trim((string)($_POST['note'] ?? 'Admin adjustment'));

        $result = $this->finance->postEntry($clubId, 'MANUAL_ADMIN_ADJUSTMENT', $amount, $note, null, 'ADMIN', (int)Auth::id());
        $this->redirectResult($clubId, $result);
    }

    private function canManageClub(int $clubId): bool {
        if (Auth::isAdmin()) return true;
        $club = $this->db->fetchOne("SELECT owner_user_id FROM clubs WHERE id = ?", [$clubId]);
        return $club && (int)($club['owner_user_id'] ?? 0) === (int)Auth::id();
    }

    private function redirectResult(int $clubId, array $result): void {
        if (!empty($result['ok'])) {
            $this->redirect('/finance?club_id=' . $clubId . '&success=Operation completed');
        }
        $this->redirect('/finance?club_id=' . $clubId . '&error=' . urlencode((string)($result['error'] ?? 'Operation failed')));
    }
}
