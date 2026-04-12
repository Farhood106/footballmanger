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
        $offers = $this->applicationModel->getOffersForCoach((int)Auth::id());

        $this->view('manager/apply', [
            'clubs' => $clubs,
            'history' => $history,
            'offers' => $offers
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
                'offers' => $this->applicationModel->getOffersForCoach($userId),
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
            'offers' => $this->applicationModel->getOffersForCoach($userId),
            'success' => 'درخواست مربیگری ثبت شد و در انتظار بررسی مالک/مدیر سایت است.'
        ]);
    }

    public function manageApplications(): void {
        $this->requireAuth();
        if (!Auth::isAdmin() && Auth::gameRole() !== 'OWNER') {
            $this->redirect('/dashboard');
        }

        $pending = $this->applicationModel->getPendingForReviewer((int)Auth::id(), Auth::isAdmin());
        $offers = $this->applicationModel->getOffersForReviewer((int)Auth::id(), Auth::isAdmin());
        $this->view('manager/manage-applications', ['pending' => $pending, 'offers' => $offers]);
    }

    public function approveApplication(): void {
        $this->review(true);
    }

    public function rejectApplication(): void {
        $this->review(false);
    }


    public function sendOffer(): void {
        $this->requireAuth();
        if (!Auth::isAdmin() && Auth::gameRole() !== 'OWNER') {
            $this->redirect('/dashboard');
        }

        $applicationId = (int)($_POST['application_id'] ?? 0);
        $salary = (int)($_POST['offered_salary_per_cycle'] ?? -1);
        $cycles = (int)($_POST['offered_contract_length_cycles'] ?? 0);
        $objective = trim((string)($_POST['club_objective'] ?? ''));
        $bonusPromotion = (int)($_POST['bonus_promotion'] ?? 0);
        $bonusTitle = (int)($_POST['bonus_title'] ?? 0);

        $result = $this->applicationModel->sendOffer(
            $applicationId,
            (int)Auth::id(),
            Auth::isAdmin(),
            $salary,
            $cycles,
            $objective,
            $bonusPromotion,
            $bonusTitle
        );

        $this->renderManageApplicationsResult($result);
    }

    public function respondOfferAccept(int $id): void {
        $this->respondOffer($id, 'accept');
    }

    public function respondOfferReject(int $id): void {
        $this->respondOffer($id, 'reject');
    }

    public function respondOfferCounter(int $id): void {
        $this->respondOffer($id, 'counter');
    }

    private function respondOffer(int $id, string $action): void {
        $this->requireAuth();

        $result = $this->applicationModel->respondToOffer(
            $id,
            (int)Auth::id(),
            Auth::isAdmin(),
            $action,
            (int)($_POST['offered_salary_per_cycle'] ?? 0),
            (int)($_POST['offered_contract_length_cycles'] ?? 0),
            trim((string)($_POST['club_objective'] ?? '')),
            (int)($_POST['bonus_promotion'] ?? 0),
            (int)($_POST['bonus_title'] ?? 0)
        );

        $clubs = $this->clubModel->getUnmanaged();
        foreach ($clubs as &$club) {
            $club['expectation'] = $this->applicationModel->getExpectationByClub((int)$club['id']);
        }

        $this->view('manager/apply', [
            'clubs' => $clubs,
            'history' => $this->applicationModel->getByCoach((int)Auth::id()),
            'offers' => $this->applicationModel->getOffersForCoach((int)Auth::id()),
            'success' => !empty($result['ok']) ? 'پاسخ به پیشنهاد ثبت شد.' : null,
            'error' => !empty($result['ok']) ? null : ($result['error'] ?? 'امکان انجام این عملیات وجود ندارد.')
        ]);
    }

    private function renderManageApplicationsResult(array $result): void {
        $this->view('manager/manage-applications', [
            'pending' => $this->applicationModel->getPendingForReviewer((int)Auth::id(), Auth::isAdmin()),
            'offers' => $this->applicationModel->getOffersForReviewer((int)Auth::id(), Auth::isAdmin()),
            'success' => !empty($result['ok']) ? 'عملیات انجام شد.' : null,
            'error' => !empty($result['ok']) ? null : ($result['error'] ?? 'امکان انجام این عملیات وجود ندارد.')
        ]);
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
                'offers' => $this->applicationModel->getOffersForReviewer((int)Auth::id(), Auth::isAdmin()),
                'error' => 'وارد کردن دلیل رد درخواست الزامی است.'
            ]);
            return;
        }

        $ok = $approve
            ? $this->applicationModel->approve($id, (int)Auth::id(), Auth::isAdmin())
            : $this->applicationModel->reject($id, (int)Auth::id(), Auth::isAdmin(), $reason);

        $this->view('manager/manage-applications', [
            'pending' => $this->applicationModel->getPendingForReviewer((int)Auth::id(), Auth::isAdmin()),
            'offers' => $this->applicationModel->getOffersForReviewer((int)Auth::id(), Auth::isAdmin()),
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
