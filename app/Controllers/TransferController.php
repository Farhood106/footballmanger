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
        $playerContexts = [];
        foreach ($availablePlayers as $p) {
            $playerContexts[(int)$p['id']] = [
                'pricing' => $this->transferModel->buildPricingContext($p),
                'disposition' => $this->transferModel->getDispositionForPlayer($p),
            ];
        }

        $this->view('transfer/market', [
            'players' => $availablePlayers,
            'transfers' => $myTransfers,
            'incoming_offers' => $incomingOffers,
            'squad' => $mySquad,
            'club_id' => $clubId,
            'player_contexts' => $playerContexts,
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

        $pricing = $this->transferModel->buildPricingContext($player);
        $minAllowed = (int)$pricing['min_accept'];
        $maxAllowed = (int)$pricing['max_reasonable'];
        if ($amount < $minAllowed || $amount > $maxAllowed) {
            $this->json(['error' => 'پیشنهاد باید در بازه منطقی ارزش بازار باشد'], 400);
        }

        $transferId = $this->transferModel->makeBid($playerId, $player['club_id'], $clubId, $amount);
        if ($transferId <= 0) {
            $this->json(['error' => 'امکان ثبت پیشنهاد وجود ندارد'], 400);
        }
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
        if (!$this->canRespondToOffer($transfer, true)) {
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
        if (!$this->canRespondToOffer($transfer, false)) {
            $this->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        $this->transferModel->reject($transferId);
        $this->json(['success' => true, 'message' => 'پیشنهاد رد شد']);
    }

    public function counterBid(int $transferId): void {
        if (!Auth::check()) {
            $this->json(['error' => 'Unauthorized'], 401);
        }

        $transfer = $this->transferModel->find($transferId);
        if (!$transfer) {
            $this->json(['error' => 'انتقال یافت نشد'], 404);
        }
        if ((string)($transfer['status'] ?? '') !== 'PENDING') {
            $this->json(['error' => 'این پیشنهاد قابل کانتر نیست'], 400);
        }
        if (!$this->canManageClub((int)$transfer['from_club_id'])) {
            $this->json(['error' => 'دسترسی غیرمجاز'], 403);
        }

        $counterFee = (int)($_POST['counter_fee'] ?? 0);
        if ($counterFee <= 0) {
            $this->json(['error' => 'مبلغ کانتر نامعتبر است'], 400);
        }
        $player = $this->playerModel->find((int)$transfer['player_id']);
        if (!$player) {
            $this->json(['error' => 'بازیکن یافت نشد'], 404);
        }
        $pricing = $this->transferModel->buildPricingContext($player);
        if ($counterFee < (int)$pricing['min_accept'] || $counterFee > (int)$pricing['max_reasonable']) {
            $this->json(['error' => 'کانتر باید در بازه منطقی باشد'], 400);
        }

        $ok = $this->transferModel->counter($transferId, (int)$transfer['from_club_id'], $counterFee);
        if (!$ok) {
            $this->json(['error' => 'ثبت کانتر با خطا مواجه شد'], 422);
        }
        $this->json(['success' => true, 'message' => 'پیشنهاد متقابل ثبت شد']);
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

    private function canRespondToOffer(array $transfer, bool $isAccept): bool {
        $status = (string)($transfer['status'] ?? '');
        if ($status === 'PENDING') {
            return $this->canManageClub((int)$transfer['from_club_id']);
        }
        if ($status === 'COUNTERED') {
            return $this->canManageClub((int)$transfer['to_club_id']);
        }
        return false;
    }
}
