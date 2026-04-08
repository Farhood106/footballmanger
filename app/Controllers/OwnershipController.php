<?php
// app/Controllers/OwnershipController.php

class OwnershipController extends Controller {
    private ClubModel $clubModel;
    private OwnershipRequestModel $requestModel;

    public function __construct() {
        parent::__construct();
        $this->clubModel = new ClubModel();
        $this->requestModel = new OwnershipRequestModel();
    }

    public function requestForm(): void {
        $this->requireAuth();

        $clubs = $this->clubModel->getUnowned();
        $requests = $this->requestModel->getUserRequests((int)Auth::id());

        $this->view('ownership/request', [
            'clubs' => $clubs,
            'requests' => $requests,
        ]);
    }

    public function submitRequest(): void {
        $this->requireAuth();

        $clubId = (int)($_POST['club_id'] ?? 0);
        $offer = (int)($_POST['offer_amount'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if ($clubId <= 0) {
            $this->requestFormWithError('باشگاه را انتخاب کنید.');
            return;
        }

        $club = $this->clubModel->find($clubId);
        if (!$club || !empty($club['owner_user_id'])) {
            $this->requestFormWithError('این باشگاه دیگر برای خرید در دسترس نیست.');
            return;
        }

        $userId = (int)Auth::id();
        if ($this->requestModel->hasPendingRequest($userId, $clubId)) {
            $this->requestFormWithError('برای این باشگاه قبلاً درخواست فعال ثبت شده است.');
            return;
        }

        $this->requestModel->createRequest($userId, $clubId, $offer, $message);
        $this->promoteCoachToOwner($userId);

        $clubs = $this->clubModel->getUnowned();
        $requests = $this->requestModel->getUserRequests($userId);
        $this->view('ownership/request', [
            'clubs' => $clubs,
            'requests' => $requests,
            'success' => 'درخواست خرید باشگاه ثبت شد و در انتظار بررسی است.'
        ]);
    }

    public function manageRequests(): void {
        $this->requireAuth();

        $userId = (int)Auth::id();
        $isAdmin = Auth::isAdmin();
        $isOwner = Auth::gameRole() === 'OWNER';

        if (!$isAdmin && !$isOwner) {
            $this->redirect('/dashboard');
        }

        $pending = $isAdmin
            ? $this->requestModel->getPendingForAdmin()
            : $this->requestModel->getPendingForOwner($userId);

        $this->view('ownership/manage', [
            'pending' => $pending,
            'can_admin_override' => $isAdmin
        ]);
    }

    public function approveRequest(): void {
        $this->reviewRequestAction(true);
    }

    public function rejectRequest(): void {
        $this->reviewRequestAction(false);
    }

    private function requestFormWithError(string $error): void {
        $clubs = $this->clubModel->getUnowned();
        $requests = $this->requestModel->getUserRequests((int)Auth::id());

        $this->view('ownership/request', [
            'clubs' => $clubs,
            'requests' => $requests,
            'error' => $error
        ]);
    }

    private function promoteCoachToOwner(int $userId): void {
        if (Auth::gameRole() === 'COACH') {
            Database::getInstance()->execute(
                "UPDATE users SET game_role = 'OWNER' WHERE id = ?",
                [$userId]
            );
            unset($_SESSION['auth_token']);
            Auth::login($userId);
        }
    }

    private function reviewRequestAction(bool $approve): void {
        $this->requireAuth();

        $requestId = (int)($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            $this->redirect('/ownership/manage');
        }

        $ok = $approve
            ? $this->requestModel->approve($requestId, (int)Auth::id(), Auth::isAdmin())
            : $this->requestModel->reject($requestId, (int)Auth::id(), Auth::isAdmin());

        $pending = Auth::isAdmin()
            ? $this->requestModel->getPendingForAdmin()
            : $this->requestModel->getPendingForOwner((int)Auth::id());

        $this->view('ownership/manage', [
            'pending' => $pending,
            'can_admin_override' => Auth::isAdmin(),
            'success' => $ok ? 'عملیات با موفقیت انجام شد.' : null,
            'error' => $ok ? null : 'اجازه یا وضعیت مناسب برای این عملیات وجود ندارد.'
        ]);
    }
}
