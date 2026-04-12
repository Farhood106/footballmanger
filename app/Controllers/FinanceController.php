<?php
// app/Controllers/FinanceController.php

class FinanceController extends Controller {
    private Database $db;
    private FinanceService $finance;
    private const ALLOWED_TIERS = ['main', 'secondary', 'minor'];

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

        $brandName = trim((string)($_POST['brand_name'] ?? ''));
        $tier = $this->sanitizeTier((string)($_POST['tier'] ?? 'minor'));
        if ($brandName === '') {
            $this->redirect('/finance?club_id=' . $clubId . '&error=' . urlencode('Brand name is required.'));
        }

        $this->db->insert('club_sponsors', [
            'club_id' => $clubId,
            'tier' => $tier,
            'brand_name' => $brandName,
            'description' => trim((string)($_POST['description'] ?? '')),
            'contact_link' => $this->sanitizeUrl((string)($_POST['contact_link'] ?? '')),
            'banner_url' => $this->sanitizeUrl((string)($_POST['banner_url'] ?? '')),
            'is_active' => 1,
        ]);

        $this->redirect('/finance?club_id=' . $clubId . '&success=Sponsor added');
    }

    public function updateSponsor(): void {
        $this->requireAuth();
        $clubId = (int)($_POST['club_id'] ?? 0);
        $sponsorId = (int)($_POST['sponsor_id'] ?? 0);
        if (!$this->canManageClub($clubId)) {
            $this->redirect('/finance?error=Unauthorized');
        }
        $sponsor = $this->db->fetchOne("SELECT id, club_id FROM club_sponsors WHERE id = ?", [$sponsorId]);
        if (!$sponsor || (int)$sponsor['club_id'] !== $clubId) {
            $this->redirect('/finance?club_id=' . $clubId . '&error=' . urlencode('Sponsor not found.'));
        }

        $brandName = trim((string)($_POST['brand_name'] ?? ''));
        if ($brandName === '') {
            $this->redirect('/finance?club_id=' . $clubId . '&error=' . urlencode('Brand name is required.'));
        }

        $this->db->execute(
            "UPDATE club_sponsors
             SET tier = ?, brand_name = ?, description = ?, contact_link = ?, banner_url = ?, is_active = ?
             WHERE id = ?",
            [
                $this->sanitizeTier((string)($_POST['tier'] ?? 'minor')),
                $brandName,
                trim((string)($_POST['description'] ?? '')),
                $this->sanitizeUrl((string)($_POST['contact_link'] ?? '')),
                $this->sanitizeUrl((string)($_POST['banner_url'] ?? '')),
                !empty($_POST['is_active']) ? 1 : 0,
                $sponsorId
            ]
        );

        $this->redirect('/finance?club_id=' . $clubId . '&success=Sponsor updated');
    }

    public function toggleSponsor(): void {
        $this->requireAuth();
        $clubId = (int)($_POST['club_id'] ?? 0);
        $sponsorId = (int)($_POST['sponsor_id'] ?? 0);
        if (!$this->canManageClub($clubId)) {
            $this->redirect('/finance?error=Unauthorized');
        }
        $sponsor = $this->db->fetchOne("SELECT id, club_id, is_active FROM club_sponsors WHERE id = ?", [$sponsorId]);
        if (!$sponsor || (int)$sponsor['club_id'] !== $clubId) {
            $this->redirect('/finance?club_id=' . $clubId . '&error=' . urlencode('Sponsor not found.'));
        }

        $next = (int)($sponsor['is_active'] ?? 0) === 1 ? 0 : 1;
        $this->db->execute("UPDATE club_sponsors SET is_active = ? WHERE id = ?", [$next, $sponsorId]);
        $this->redirect('/finance?club_id=' . $clubId . '&success=' . urlencode($next === 1 ? 'Sponsor activated' : 'Sponsor deactivated'));
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

    private function sanitizeTier(string $tier): string {
        $normalized = strtolower(trim($tier));
        if (!in_array($normalized, self::ALLOWED_TIERS, true)) {
            return 'minor';
        }
        return $normalized;
    }

    private function sanitizeUrl(string $url): ?string {
        $value = trim($url);
        if ($value === '') return null;
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        return $value;
    }
}
