<?php
// app/Controllers/DashboardController.php

class DashboardController extends Controller {
    private UserModel $userModel;
    private ClubModel $clubModel;
    private MatchModel $matchModel;
    private NotificationModel $notificationModel;
    private WorldHistoryService $historyService;

    public function __construct() {
        parent::__construct();
        $this->userModel = new UserModel();
        $this->clubModel = new ClubModel();
        $this->matchModel = new MatchModel();
        $this->notificationModel = new NotificationModel();
        $this->historyService = new WorldHistoryService();
    }

    public function index(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $club = $this->userModel->getClub(Auth::id());
        if (!$club) {
            if (Auth::gameRole() === 'OWNER') {
                $this->redirect('/ownership/request');
            }
            $this->redirect('/club/select');
        }

        $clubDetails = $this->clubModel->getWithDetails($club['id']);
        $upcomingMatches = $this->matchModel->getUpcoming($club['id'], 3);
        $recentMatches = $this->matchModel->getByClub($club['id'], 5);
        $notifications = $this->notificationModel->getForUser(Auth::id(), true);
        $finances = $this->clubModel->getFinances($club['id']);
        $activeSponsors = $this->clubModel->getSponsors((int)$club['id'], true);
        $recentRecognitions = $this->historyService->getRecentRecognitionsForClub((int)$club['id'], 5);

        $this->view('dashboard/index', [
            'club' => $clubDetails,
            'upcoming' => $upcomingMatches,
            'recent' => $recentMatches,
            'notifications' => $notifications,
            'finances' => $finances,
            'active_sponsors' => $activeSponsors,
            'recent_recognitions' => $recentRecognitions
        ]);
    }
}
