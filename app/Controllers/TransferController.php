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
        $incomingOffers = $this->transferModel->getIncomingOffers($clubId);
        $mySquad = $this->clubModel->getSquad($clubId);

        $this->view('transfer/market', [
            'players' => $availablePlayers,
            'transfers' => $myTransfers,
            'incoming_offers' => $incomingOffers,
            'squad' => $mySquad,
            'club_id' => $clubId,
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
        if ((int)($player['is_transfer_listed'] ?? 0) !== 1) {
            $this->json(['error' => 'بازیکن در لیست فروش نیست'], 400);
        }

        $club = $this->clubModel->find($clubId);

        if (($club['balance'] ?? 0) < $amount) {
            $this->json(['error' => 'بودجه کافی ندارید'], 400);
        }

        $marketValue = max(1, (int)($player['market_value'] ?? 1));
        $minAllowed = (int)floor($marketValue * 0.70);
        $maxAllowed = (int)ceil($marketValue * 1.80);
        if ($amount < $minAllowed || $amount > $maxAllowed) {
            $this->json(['error' => 'پیشنهاد باید در بازه منطقی ارزش بازار باشد'], 400);
        }

        $transferId = $this->transferModel->makeBid($playerId, $player['club_id'], $clubId, $amount);
        $this->json(['success' => true, 'transfer_id' => $transferId]);
    }

    public function acceptBid(int $transferId): void {
        if (!Auth::check()) {
            $this->json(['error' => 'Unauthorized'], 401);
        }

        $transfer = $this->transferModel->find($transferId);
        if (!$transfer) {
            $this->json(['error' => 'انتقال یافت نشد'], 404);
        }
        if (!$this->canManageClub((int)$transfer['from_club_id'])) {
            $this->json(['error' => 'دسترسی غیرمجاز'], 403);
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

        $transfer = $this->transferModel->find($transferId);
        if (!$transfer) {
            $this->json(['error' => 'انتقال یافت نشد'], 404);
        }
        if (!$this->canManageClub((int)$transfer['from_club_id'])) {
            $this->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        $this->transferModel->reject($transferId);
        $this->json(['success' => true, 'message' => 'پیشنهاد رد شد']);
    }

    public function setListed(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }
        $clubId = $this->getClubId();
        $playerId = (int)($_POST['player_id'] ?? 0);
        $listed = ((int)($_POST['listed'] ?? 0) === 1);
        $askingPrice = max(1, (int)($_POST['asking_price'] ?? 0));

        if (!$this->canManageClub($clubId)) {
            $this->redirect('/transfers');
        }

        $this->transferModel->setTransferListed($playerId, $clubId, $listed, $askingPrice);
        $this->redirect('/transfers');
    }

    private function getClubId(): int {
        $club = (new UserModel())->getClub(Auth::id());
        return $club['id'] ?? 0;
    }

    private function canManageClub(int $clubId): bool {
        if (Auth::isAdmin()) return true;
        $club = $this->clubModel->find($clubId);
        if (!$club) return false;
        $uid = (int)Auth::id();
        return (int)($club['owner_user_id'] ?? 0) === $uid || (int)($club['manager_user_id'] ?? 0) === $uid;
    }
}
