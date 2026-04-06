<?php
// app/Controllers/DashboardController.php

class DashboardController extends Controller {
    private UserModel $userModel;
    private ClubModel $clubModel;
    private MatchModel $matchModel;
    private NotificationModel $notificationModel;

    public function __construct() {
        parent::__construct();
        $this->userModel = new UserModel();
        $this->clubModel = new ClubModel();
        $this->matchModel = new MatchModel();
        $this->notificationModel = new NotificationModel();
    }

    public function index(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        $club = $this->userModel->getClub(Auth::id());
        if (!$club) {
            $this->redirect('/club/select');
        }

        $clubDetails = $this->clubModel->getWithDetails($club['id']);
        $upcomingMatches = $this->matchModel->getUpcoming($club['id'], 3);
        $recentMatches = $this->matchModel->getByClub($club['id'], 5);
        $notifications = $this->notificationModel->getForUser(Auth::id(), true);
        $finances = $this->clubModel->getFinances($club['id']);

        $this->view('dashboard/index', [
            'club' => $clubDetails,
            'upcoming' => $upcomingMatches,
            'recent' => $recentMatches,
            'notifications' => $notifications,
            'finances' => $finances
        ]);
    }
}
