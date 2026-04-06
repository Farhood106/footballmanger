<?php
// app/Controllers/MatchController.php

class MatchController extends Controller {
    private MatchModel $matchModel;
    private ClubModel $clubModel;

    public function __construct() {
        parent::__construct();
        $this->matchModel = new MatchModel();
        $this->clubModel = new ClubModel();
    }

    public function fixtures(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $clubId = $this->getClubId();
        $upcoming = $this->matchModel->getUpcoming($clubId, 10);
        $recent = $this->matchModel->getByClub($clubId, 10);

        $this->view('match/fixtures', [
            'upcoming' => $upcoming,
            'recent' => $recent
        ]);
    }

    public function detail(int $matchId): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $match = $this->matchModel->getWithEvents($matchId);
        if (!$match) {
            $this->redirect('/matches');
        }

        $this->view('match/detail', ['match' => $match]);
    }

    private function getClubId(): int {
        $club = (new UserModel())->getClub(Auth::id());
        return $club['id'] ?? 0;
    }
}
