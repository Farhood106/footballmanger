<?php
// app/Controllers/TransferController.php

class TransferController extends Controller {
    private TransferModel $transferModel;
    private PlayerModel $playerModel;
    private ClubModel $clubModel;

    public function __construct() {
        parent::__construct();
        $this->transferModel = new TransferModel();
        $this->playerModel = new PlayerModel();
        $this->clubModel = new ClubModel();
    }

    public function market(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $clubId = $this->getClubId();
        $availablePlayers = $this->playerModel->getAvailableForTransfer($clubId);
        $myTransfers = $this->transferModel->getByClub($clubId);

        $this->view('transfer/market', [
            'players' => $availablePlayers,
            'transfers' => $myTransfers
        ]);
    }

    public function makeBid(): void {
        if (!Auth::check()) {
            $this->json(['error' => 'Unauthorized'], 401);
        }

        $playerId = (int)($_POST['player_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);

        if (!$playerId || $amount <= 0) {
            $this->json(['error' => 'اطلاعات نامعتبر'], 400);
        }

        $clubId = $this->getClubId();
        $player = $this->playerModel->find($playerId);
        if (!$player || (int)$player['club_id'] === 0 || (int)$player['club_id'] === $clubId) {
            $this->json(['error' => 'بازیکن در دسترس نیست'], 400);
        }

        $club = $this->clubModel->find($clubId);

        if (($club['balance'] ?? 0) < $amount) {
            $this->json(['error' => 'بودجه کافی ندارید'], 400);
        }

        $transferId = $this->transferModel->makeBid($playerId, $player['club_id'], $clubId, $amount);
        $this->json(['success' => true, 'transfer_id' => $transferId]);
    }

    public function acceptBid(int $transferId): void {
        if (!Auth::check()) {
            $this->json(['error' => 'Unauthorized'], 401);
        }

        $success = $this->transferModel->accept($transferId);
        if ($success) {
            $this->json(['success' => true, 'message' => 'نقل و انتقال تکمیل شد']);
        } else {
            $this->json(['error' => 'خطا در پردازش'], 500);
        }
    }

    public function rejectBid(int $transferId): void {
        if (!Auth::check()) {
            $this->json(['error' => 'Unauthorized'], 401);
        }

        $this->transferModel->reject($transferId);
        $this->json(['success' => true, 'message' => 'پیشنهاد رد شد']);
    }

    private function getClubId(): int {
        $club = (new UserModel())->getClub(Auth::id());
        return $club['id'] ?? 0;
    }
}
