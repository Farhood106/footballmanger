<?php
// app/Controllers/ClubHistoryController.php

class ClubHistoryController extends Controller {
    private UserModel $userModel;
    private ClubModel $clubModel;
    private WorldHistoryService $historyService;

    public function __construct() {
        parent::__construct();
        $this->userModel = new UserModel();
        $this->clubModel = new ClubModel();
        $this->historyService = new WorldHistoryService();
    }

    public function index(): void {
        $this->requireAuth();

        $club = $this->userModel->getClub(Auth::id());
        if (!$club) {
            $this->redirect('/club/select');
        }

        $clubId = (int)$club['id'];
        $clubDetails = $this->clubModel->getWithDetails($clubId);

        $this->view('club/history', [
            'club' => $clubDetails,
            'recent_recognitions' => $this->historyService->getRecentRecognitionsForClub($clubId, 10),
            'season_awards' => $this->historyService->getSeasonAwardsForClub($clubId, 20),
            'honors' => $this->historyService->getClubHonors($clubId, 25),
            'records' => $this->historyService->getClubRecords($clubId),
            'legends' => $this->historyService->getClubLegends($clubId, 20),
        ]);
    }
}
