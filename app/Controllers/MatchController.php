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

        $statsRaw = [];
        if (!empty($match['stats'])) {
            $decoded = json_decode((string)$match['stats'], true);
            if (is_array($decoded)) {
                $statsRaw = $decoded;
            }
        }

        $statKeys = ['possession', 'shots', 'shots_on_target', 'corners', 'fouls'];
        $matchStats = ['home' => [], 'away' => []];
        foreach ($statKeys as $key) {
            $matchStats['home'][$key] = $statsRaw[$key]['home'] ?? '-';
            $matchStats['away'][$key] = $statsRaw[$key]['away'] ?? '-';
        }
        $matchStats['home']['xg'] = $match['home_xg'] ?? '-';
        $matchStats['away']['xg'] = $match['away_xg'] ?? '-';

        $ratings = ['home' => [], 'away' => []];
        foreach (($match['ratings'] ?? []) as $rating) {
            $clubId = (int)($rating['club_id'] ?? 0);
            if ($clubId === (int)$match['home_club_id']) {
                $ratings['home'][] = $rating;
                continue;
            }
            if ($clubId === (int)$match['away_club_id']) {
                $ratings['away'][] = $rating;
            }
        }

        $this->view('match/detail', [
            'match' => $match,
            'events' => $match['events'] ?? [],
            'matchStats' => $matchStats,
            'ratings' => $ratings
        ]);
    }

    private function getClubId(): int {
        $club = (new UserModel())->getClub(Auth::id());
        return $club['id'] ?? 0;
    }
}
