<?php
// app/Controllers/AdminController.php

class AdminController extends Controller {
    private Database $db;
    private ClubModel $clubModel;

    public function __construct() {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->clubModel = new ClubModel();
    }

    public function index(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $stats = [
            'users' => (int)($this->db->fetchOne("SELECT COUNT(*) AS c FROM users")['c'] ?? 0),
            'clubs' => (int)($this->db->fetchOne("SELECT COUNT(*) AS c FROM clubs")['c'] ?? 0),
            'players' => (int)($this->db->fetchOne("SELECT COUNT(*) AS c FROM players")['c'] ?? 0),
        ];

        $this->view('admin/index', ['stats' => $stats]);
    }

    public function createClubForm(): void {
        $this->requireAuth();
        $this->requireAdmin();
        $this->view('admin/create-club');
    }

    public function storeClub(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $name = trim($_POST['name'] ?? '');
        $shortName = trim($_POST['short_name'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $founded = (int)($_POST['founded'] ?? 2000);
        $stadiumName = trim($_POST['stadium_name'] ?? '');
        $stadiumCapacity = (int)($_POST['stadium_capacity'] ?? 30000);

        if ($name === '' || $shortName === '' || $country === '' || $city === '' || $stadiumName === '') {
            $this->view('admin/create-club', ['error' => 'تمام فیلدهای ضروری را تکمیل کنید.']);
            return;
        }

        try {
            $this->db->insert('clubs', [
                'name' => $name,
                'short_name' => strtoupper($shortName),
                'country' => $country,
                'city' => $city,
                'founded' => $founded,
                'stadium_name' => $stadiumName,
                'stadium_capacity' => $stadiumCapacity,
                'reputation' => (int)($_POST['reputation'] ?? 50),
                'balance' => (int)($_POST['balance'] ?? 10000000),
            ]);
        } catch (Throwable $e) {
            $this->view('admin/create-club', ['error' => 'خطا در ثبت باشگاه: ' . $e->getMessage()]);
            return;
        }

        $this->view('admin/create-club', ['success' => 'باشگاه با موفقیت ایجاد شد.']);
    }

    public function createPlayerForm(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $clubs = $this->clubModel->findAll([], 'name ASC');
        $this->view('admin/create-player', ['clubs' => $clubs]);
    }

    public function storePlayer(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $clubId = (int)($_POST['club_id'] ?? 0);

        if ($firstName === '' || $lastName === '' || $clubId <= 0) {
            $clubs = $this->clubModel->findAll([], 'name ASC');
            $this->view('admin/create-player', [
                'clubs' => $clubs,
                'error' => 'نام، نام خانوادگی و باشگاه الزامی هستند.'
            ]);
            return;
        }

        try {
            $this->db->insert('players', [
                'club_id' => $clubId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'nationality' => trim($_POST['nationality'] ?? 'Iran'),
                'birth_date' => $_POST['birth_date'] ?? '2000-01-01',
                'position' => $_POST['position'] ?? 'CM',
                'preferred_foot' => $_POST['preferred_foot'] ?? 'RIGHT',
                'pace' => (int)($_POST['pace'] ?? 60),
                'shooting' => (int)($_POST['shooting'] ?? 60),
                'passing' => (int)($_POST['passing'] ?? 60),
                'dribbling' => (int)($_POST['dribbling'] ?? 60),
                'defending' => (int)($_POST['defending'] ?? 60),
                'physical' => (int)($_POST['physical'] ?? 60),
                'overall' => (int)($_POST['overall'] ?? 60),
                'potential' => (int)($_POST['potential'] ?? 75),
                'wage' => (int)($_POST['wage'] ?? 0),
                'market_value' => (int)($_POST['market_value'] ?? 0),
            ]);
        } catch (Throwable $e) {
            $clubs = $this->clubModel->findAll([], 'name ASC');
            $this->view('admin/create-player', [
                'clubs' => $clubs,
                'error' => 'خطا در ثبت بازیکن: ' . $e->getMessage()
            ]);
            return;
        }

        $clubs = $this->clubModel->findAll([], 'name ASC');
        $this->view('admin/create-player', ['clubs' => $clubs, 'success' => 'بازیکن با موفقیت ایجاد شد.']);
    }
}
