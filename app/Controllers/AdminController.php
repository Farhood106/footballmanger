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

    public function seedImportPage(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $report = $_SESSION['admin_seed_import_report'] ?? null;
        unset($_SESSION['admin_seed_import_report']);

        $this->view('admin/seed-import', [
            'seed_sets' => $this->listAvailableSeedSets(),
            'import_report' => $report,
        ]);
    }

    public function importSeed(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $dataset = trim((string)($_POST['dataset'] ?? ''));
        $dryRun = in_array(strtolower((string)($_POST['dry_run'] ?? '0')), ['1', 'true', 'yes', 'on'], true);

        $resolved = $this->resolveSeedSetDirectory($dataset);
        if (!$resolved['ok']) {
            $payload = ['ok' => false, 'error' => $resolved['error'] ?? 'Invalid dataset'];
            if ($this->wantsJson()) {
                $this->json($payload, 422);
            }
            $_SESSION['admin_seed_import_report'] = $payload;
            $this->redirect('/admin/seed');
        }

        try {
            require_once dirname(__DIR__, 2) . '/database/seeds/StructuredSeedImporter.php';
            ignore_user_abort(true);
            @set_time_limit(120);

            $importer = new StructuredSeedImporter($this->buildSeedImportPdo(), $dryRun);
            $report = $importer->importFromDirectory((string)$resolved['path']);
            $response = [
                'ok' => !empty($report['ok']),
                'dataset' => $dataset,
                'dry_run' => $dryRun,
                'report' => $report,
            ];

            if ($this->wantsJson()) {
                $this->json($response, !empty($report['ok']) ? 200 : 422);
            }
            $_SESSION['admin_seed_import_report'] = $response;
            $this->redirect('/admin/seed');
        } catch (Throwable $e) {
            $payload = [
                'ok' => false,
                'dataset' => $dataset,
                'dry_run' => $dryRun,
                'error' => 'Seed import failed: ' . $e->getMessage(),
            ];
            if ($this->wantsJson()) {
                $this->json($payload, 500);
            }
            $_SESSION['admin_seed_import_report'] = $payload;
            $this->redirect('/admin/seed');
        }
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
            $this->ensureUtf8ForTable('clubs');
            $meta = $this->getTableColumnMeta('clubs');
            $columns = array_keys($meta);
            $payload = [];

            $this->setIfColumnExists($payload, $columns, 'name', $name);
            $this->setIfColumnExists($payload, $columns, 'short_name', strtoupper($shortName));
            $this->setIfColumnExists($payload, $columns, 'country', $country);
            $this->setIfColumnExists($payload, $columns, 'city', $city);
            $this->setIfColumnExists($payload, $columns, 'founded', $founded);
            $this->setIfColumnExists($payload, $columns, 'founded_year', $founded);
            $this->setIfColumnExists($payload, $columns, 'stadium_name', $stadiumName);
            $this->setIfColumnExists($payload, $columns, 'stadium_capacity', $stadiumCapacity);
            $this->setIfColumnExists($payload, $columns, 'reputation', (int)($_POST['reputation'] ?? 50));
            $this->setIfColumnExists($payload, $columns, 'balance', (int)($_POST['balance'] ?? 10000000));

            if (empty($payload)) {
                $this->view('admin/create-club', ['error' => 'ستون‌های مورد نیاز برای ثبت باشگاه در دیتابیس پیدا نشد.']);
                return;
            }

            $this->applyNotNullDefaults($payload, $meta, 'clubs');
            $this->db->insert('clubs', $payload);
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
            $this->ensureUtf8ForTable('players');
            $meta = $this->getTableColumnMeta('players');
            $columns = array_keys($meta);
            $payload = [];

            $this->setIfColumnExists($payload, $columns, 'club_id', $clubId);
            $this->setIfColumnExists($payload, $columns, 'first_name', $firstName);
            $this->setIfColumnExists($payload, $columns, 'last_name', $lastName);
            $this->setIfColumnExists($payload, $columns, 'name', $firstName . ' ' . $lastName);
            $this->setIfColumnExists($payload, $columns, 'nationality', trim($_POST['nationality'] ?? 'Iran'));
            $this->setIfColumnExists($payload, $columns, 'birth_date', $_POST['birth_date'] ?? '2000-01-01');
            $this->setIfColumnExists($payload, $columns, 'position', $_POST['position'] ?? 'CM');
            $this->setIfColumnExists($payload, $columns, 'preferred_foot', $_POST['preferred_foot'] ?? 'RIGHT');
            $this->setIfColumnExists($payload, $columns, 'pace', (int)($_POST['pace'] ?? 60));
            $this->setIfColumnExists($payload, $columns, 'shooting', (int)($_POST['shooting'] ?? 60));
            $this->setIfColumnExists($payload, $columns, 'passing', (int)($_POST['passing'] ?? 60));
            $this->setIfColumnExists($payload, $columns, 'dribbling', (int)($_POST['dribbling'] ?? 60));
            $this->setIfColumnExists($payload, $columns, 'defending', (int)($_POST['defending'] ?? 60));
            $this->setIfColumnExists($payload, $columns, 'physical', (int)($_POST['physical'] ?? 60));
            $this->setIfColumnExists($payload, $columns, 'overall', (int)($_POST['overall'] ?? 60));
            $this->setIfColumnExists($payload, $columns, 'potential', (int)($_POST['potential'] ?? 75));
            $this->setIfColumnExists($payload, $columns, 'wage', (int)($_POST['wage'] ?? 0));
            $this->setIfColumnExists($payload, $columns, 'market_value', (int)($_POST['market_value'] ?? 0));

            $this->applyNotNullDefaults($payload, $meta, 'players');
            $this->db->insert('players', $payload);
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

    private function getTableColumns(string $table): array {
        $rows = $this->db->fetchAll("SHOW COLUMNS FROM `{$table}`");
        return array_map(fn($r) => $r['Field'], $rows);
    }

    private function getTableColumnMeta(string $table): array {
        $rows = $this->db->fetchAll("SHOW COLUMNS FROM `{$table}`");
        $meta = [];
        foreach ($rows as $row) {
            $meta[$row['Field']] = $row;
        }
        return $meta;
    }

    private function setIfColumnExists(array &$payload, array $columns, string $column, mixed $value): void {
        if (in_array($column, $columns, true)) {
            $payload[$column] = $value;
        }
    }

    private function applyNotNullDefaults(array &$payload, array $meta, string $table): void {
        foreach ($meta as $field => $info) {
            $isMissing = !array_key_exists($field, $payload);
            $isNotNull = strtoupper((string)$info['Null']) === 'NO';
            $hasDefault = $info['Default'] !== null;
            $isAuto = stripos((string)$info['Extra'], 'auto_increment') !== false;

            if (!$isMissing || !$isNotNull || $hasDefault || $isAuto) {
                continue;
            }

            $type = strtolower((string)$info['Type']);
            if (str_ends_with($field, '_id')) {
                $payload[$field] = $this->resolveForeignKeyDefault($field);
            } elseif (str_starts_with($type, 'int') || str_starts_with($type, 'bigint')) {
                $payload[$field] = 0;
            } elseif (str_starts_with($type, 'decimal') || str_starts_with($type, 'float') || str_starts_with($type, 'double')) {
                $payload[$field] = 0;
            } elseif (str_starts_with($type, 'date')) {
                $payload[$field] = date('Y-m-d');
            } elseif (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
                $payload[$field] = date('Y-m-d H:i:s');
            } elseif (str_starts_with($type, 'enum(')) {
                preg_match_all("/'([^']+)'/", $type, $matches);
                $payload[$field] = $matches[1][0] ?? '';
            } else {
                $payload[$field] = '-';
            }
        }
    }

    private function resolveForeignKeyDefault(string $field): int {
        return match ($field) {
            'user_id', 'manager_user_id', 'owner_user_id', 'reviewed_by', 'initiated_by' => (int)(Auth::id() ?? 1),
            'competition_id' => $this->firstIdOrFallback('competitions'),
            'season_id' => $this->firstIdOrFallback('seasons'),
            'club_id', 'from_club_id', 'to_club_id' => $this->firstIdOrFallback('clubs'),
            'player_id', 'assist_player_id', 'captain', 'corner_taker', 'freekick_taker', 'penalty_taker' => $this->firstIdOrFallback('players'),
            default => 1
        };
    }

    private function firstIdOrFallback(string $table): int {
        try {
            $row = $this->db->fetchOne("SELECT id FROM `{$table}` ORDER BY id ASC LIMIT 1");
            return (int)($row['id'] ?? 1);
        } catch (Throwable $e) {
            return 1;
        }
    }

    private function ensureUtf8ForTable(string $table): void {
        try {
            $this->db->execute(
                "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            // اگر دسترسی ALTER یا تغییر charset ممکن نبود، ادامه می‌دهیم
        }
    }

    private function seedSetsBaseDir(): string {
        return dirname(__DIR__, 2) . '/database/seed_sets';
    }

    private function listAvailableSeedSets(): array {
        $base = $this->seedSetsBaseDir();
        if (!is_dir($base)) {
            return [];
        }

        $items = [];
        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $dir = $base . '/' . $entry;
            if (!is_dir($dir)) {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $entry)) {
                continue;
            }
            $required = ['competitions.json', 'clubs.json', 'players.json'];
            $missing = [];
            foreach ($required as $file) {
                if (!is_file($dir . '/' . $file)) {
                    $missing[] = $file;
                }
            }
            $items[] = [
                'key' => $entry,
                'path' => $dir,
                'is_valid' => empty($missing),
                'missing' => $missing,
            ];
        }

        usort($items, fn($a, $b) => strcmp((string)$a['key'], (string)$b['key']));
        return $items;
    }

    private function resolveSeedSetDirectory(string $dataset): array {
        if ($dataset === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $dataset) || str_contains($dataset, '..')) {
            return ['ok' => false, 'error' => 'Dataset key is invalid.'];
        }

        $allowed = array_column($this->listAvailableSeedSets(), null, 'key');
        if (!isset($allowed[$dataset])) {
            return ['ok' => false, 'error' => 'Dataset is not in allowed seed set list.'];
        }
        if (empty($allowed[$dataset]['is_valid'])) {
            return ['ok' => false, 'error' => 'Dataset is missing required JSON files.'];
        }

        $resolved = realpath((string)$allowed[$dataset]['path']);
        $base = realpath($this->seedSetsBaseDir());
        if ($resolved === false || $base === false || !str_starts_with($resolved, $base)) {
            return ['ok' => false, 'error' => 'Dataset path is outside allowed base directory.'];
        }
        return ['ok' => true, 'path' => $resolved];
    }

    private function buildSeedImportPdo(): PDO {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $db = $config['db'];
        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
        return new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function wantsJson(): bool {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest' || (string)($_POST['response'] ?? '') === 'json';
    }
}
