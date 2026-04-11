<?php
// app/Controllers/GovernanceController.php

class GovernanceController extends Controller {
    private GovernanceService $service;

    public function __construct() {
        parent::__construct();
        $this->service = new GovernanceService();
    }

    public function index(): void {
        $this->requireAuth();

        $cases = $this->service->getCasesForUser((int)Auth::id());
        $this->view('governance/index', ['cases' => $cases]);
    }

    public function createForm(): void {
        $this->requireAuth();
        $clubs = $this->service->getEligibleClubs((int)Auth::id());
        $this->view('governance/create', ['clubs' => $clubs]);
    }

    public function createCase(): void {
        $this->requireAuth();

        $result = $this->service->createCase(
            (int)Auth::id(),
            (int)($_POST['club_id'] ?? 0),
            trim((string)($_POST['case_type'] ?? 'OTHER')),
            trim((string)($_POST['subject'] ?? '')),
            trim((string)($_POST['description'] ?? '')),
        );

        if (!$result['ok']) {
            $this->view('governance/create', [
                'clubs' => $this->service->getEligibleClubs((int)Auth::id()),
                'error' => $result['error']
            ]);
            return;
        }

        $this->redirect('/governance/cases');
    }

    public function detail(int $caseId): void {
        $this->requireAuth();
        $case = $this->service->getCaseWithDecisions($caseId);

        if (!$case) {
            $this->redirect('/governance/cases');
        }

        $uid = (int)Auth::id();
        $allowed = Auth::isAdmin() || $uid === (int)($case['owner_user_id'] ?? 0) || $uid === (int)($case['manager_user_id'] ?? 0);
        if (!$allowed) {
            $this->redirect('/governance/cases');
        }

        $this->view('governance/detail', ['case' => $case]);
    }

    public function reviewIndex(): void {
        $this->requireAuth();
        $this->requireAdmin();

        $cases = $this->service->getOpenCasesForReview();
        $this->view('governance/review', ['cases' => $cases]);
    }

    public function resolve(int $caseId): void {
        $this->requireAuth();
        $this->requireAdmin();

        $result = $this->service->resolveCase(
            $caseId,
            (int)Auth::id(),
            trim((string)($_POST['decision_type'] ?? 'CASE_UPHELD')),
            trim((string)($_POST['decision_summary'] ?? '')),
            (int)($_POST['penalty_amount'] ?? 0),
            (int)($_POST['compensation_amount'] ?? 0)
        );

        if (!$result['ok']) {
            $this->view('governance/review', [
                'cases' => $this->service->getOpenCasesForReview(),
                'error' => $result['error']
            ]);
            return;
        }

        $this->redirect('/governance/review');
    }
}
