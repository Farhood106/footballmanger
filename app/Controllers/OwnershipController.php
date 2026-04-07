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

        if (Auth::gameRole() !== 'OWNER') {
            $this->redirect('/dashboard');
        }

        $clubs = $this->clubModel->getUnowned();
        $requests = $this->requestModel->getUserRequests((int)Auth::id());

        $this->view('ownership/request', [
            'clubs' => $clubs,
            'requests' => $requests,
        ]);
    }

    public function submitRequest(): void {
        $this->requireAuth();

        if (Auth::gameRole() !== 'OWNER') {
            $this->redirect('/dashboard');
        }

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

        $clubs = $this->clubModel->getUnowned();
        $requests = $this->requestModel->getUserRequests($userId);
        $this->view('ownership/request', [
            'clubs' => $clubs,
            'requests' => $requests,
            'success' => 'درخواست خرید باشگاه ثبت شد و در انتظار بررسی است.'
        ]);
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
}
