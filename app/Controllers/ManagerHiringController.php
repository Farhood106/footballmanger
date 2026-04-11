<?php
// app/Controllers/ManagerHiringController.php

class ManagerHiringController extends Controller {
    private ClubModel $clubModel;
    private ManagerApplicationModel $applicationModel;

    public function __construct() {
        parent::__construct();
        $this->clubModel = new ClubModel();
        $this->applicationModel = new ManagerApplicationModel();
    }

    public function expectations(): void {
        $this->requireAuth();
        if (!Auth::isAdmin() && Auth::gameRole() !== 'OWNER') {
            $this->redirect('/dashboard');
        }

        $clubs = Auth::isAdmin()
            ? $this->clubModel->findAll([], 'name ASC')
            : $this->dbOwnerClubs((int)Auth::id());

        $selectedClubId = (int)($_GET['club_id'] ?? ($clubs[0]['id'] ?? 0));
        $expectation = $selectedClubId ? $this->applicationModel->getExpectationByClub($selectedClubId) : null;

        $this->view('manager/expectations', [
            'clubs' => $clubs,
            'selected_club_id' => $selectedClubId,
            'expectation' => $expectation
        ]);
    }

    public function saveExpectations(): void {
        $this->requireAuth();
        if (!Auth::isAdmin() && Auth::gameRole() !== 'OWNER') {
            $this->redirect('/dashboard');
        }

        $clubId = (int)($_POST['club_id'] ?? 0);
        if ($clubId <= 0) {
            $this->redirect('/manager/expectations');
        }

        if (!Auth::isAdmin() && !$this->isClubOwner((int)Auth::id(), $clubId)) {
            $this->redirect('/manager/expectations');
        }

        $this->applicationModel->upsertExpectation(
            $clubId,
            (int)Auth::id(),
            trim($_POST['title'] ?? 'شرح همکاری مربیگری'),
            trim($_POST['expectations'] ?? ''),
            trim($_POST['duties'] ?? ''),
            trim($_POST['commitments'] ?? '')
        );

        $clubs = Auth::isAdmin()
            ? $this->clubModel->findAll([], 'name ASC')
            : $this->dbOwnerClubs((int)Auth::id());

        $this->view('manager/expectations', [
            'clubs' => $clubs,
            'selected_club_id' => $clubId,
            'expectation' => $this->applicationModel->getExpectationByClub($clubId),
            'success' => 'تعهدات و انتظارات با موفقیت ذخیره شد.'
        ]);
    }

    public function myApplications(): void {
        $this->requireAuth();

        $clubs = $this->clubModel->getUnmanaged();
        foreach ($clubs as &$club) {
            $club['expectation'] = $this->applicationModel->getExpectationByClub((int)$club['id']);
        }

        $history = $this->applicationModel->getByCoach((int)Auth::id());

        $this->view('manager/apply', [
            'clubs' => $clubs,
            'history' => $history
        ]);
    }

    public function submitApplication(): void {
        $this->requireAuth();

        $clubId = (int)($_POST['club_id'] ?? 0);
        if ($clubId <= 0) {
            $this->redirect('/manager/apply');
        }

        $userId = (int)Auth::id();
        if ($this->applicationModel->hasPendingApplication($clubId, $userId)) {
            $clubs = $this->clubModel->getUnmanaged();
            foreach ($clubs as &$club) {
                $club['expectation'] = $this->applicationModel->getExpectationByClub((int)$club['id']);
            }

            $this->view('manager/apply', [
                'clubs' => $clubs,
                'history' => $this->applicationModel->getByCoach($userId),
                'error' => 'برای این باشگاه قبلاً درخواست فعال ثبت کرده‌اید.'
            ]);
            return;
        }

        $this->applicationModel->submitApplication(
            $clubId,
            $userId,
            trim($_POST['proposed_expectations'] ?? ''),
            trim($_POST['proposed_duties'] ?? ''),
            trim($_POST['proposed_commitments'] ?? ''),
            trim($_POST['cover_letter'] ?? '')
        );

        $clubs = $this->clubModel->getUnmanaged();
        foreach ($clubs as &$club) {
            $club['expectation'] = $this->applicationModel->getExpectationByClub((int)$club['id']);
        }

        $this->view('manager/apply', [
            'clubs' => $clubs,
            'history' => $this->applicationModel->getByCoach($userId),
            'success' => 'درخواست مربیگری ثبت شد و در انتظار بررسی مالک/مدیر سایت است.'
        ]);
    }

    public function manageApplications(): void {
        $this->requireAuth();
        if (!Auth::isAdmin() && Auth::gameRole() !== 'OWNER') {
            $this->redirect('/dashboard');
        }

        $pending = $this->applicationModel->getPendingForReviewer((int)Auth::id(), Auth::isAdmin());
        $this->view('manager/manage-applications', ['pending' => $pending]);
    }

    public function approveApplication(): void {
        $this->review(true);
    }

    public function rejectApplication(): void {
        $this->review(false);
    }

    private function review(bool $approve): void {
        $this->requireAuth();

        $id = (int)($_POST['application_id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/manager/applications/manage');
        }

        $reason = trim((string)($_POST['rejection_reason'] ?? ''));

        if (!$approve && $reason === '') {
            $this->view('manager/manage-applications', [
                'pending' => $this->applicationModel->getPendingForReviewer((int)Auth::id(), Auth::isAdmin()),
                'error' => 'وارد کردن دلیل رد درخواست الزامی است.'
            ]);
            return;
        }

        $ok = $approve
            ? $this->applicationModel->approve($id, (int)Auth::id(), Auth::isAdmin())
            : $this->applicationModel->reject($id, (int)Auth::id(), Auth::isAdmin(), $reason);

        $this->view('manager/manage-applications', [
            'pending' => $this->applicationModel->getPendingForReviewer((int)Auth::id(), Auth::isAdmin()),
            'success' => $ok ? 'عملیات انجام شد.' : null,
            'error' => $ok ? null : 'امکان انجام این عملیات وجود ندارد.'
        ]);
    }

    private function isClubOwner(int $userId, int $clubId): bool {
        $club = $this->clubModel->find($clubId);
        return $club && (int)($club['owner_user_id'] ?? 0) === $userId;
    }

    private function dbOwnerClubs(int $ownerId): array {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM clubs WHERE owner_user_id = ? ORDER BY name ASC",
            [$ownerId]
        );
    }
}
