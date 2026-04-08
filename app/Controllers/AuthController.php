<?php
// app/Controllers/AuthController.php

class AuthController extends Controller {
    private UserModel $userModel;
    private ClubModel $clubModel;

    public function __construct() {
        parent::__construct();
        $this->userModel = new UserModel();
        $this->clubModel = new ClubModel();
    }

    public function showLogin(): void {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/login');
    }

    public function login(): void {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $this->view('auth/login', ['error' => 'ایمیل و رمز عبور الزامی است']);
            return;
        }

        $user = $this->userModel->findByEmail($email);
        if (!$user || !$this->userModel->verifyPassword($password, $user['password_hash'] ?? null)) {
            $this->view('auth/login', ['error' => 'اطلاعات ورود نادرست است']);
            return;
        }

        Auth::login((int)$user['id']);
        $this->userModel->updateLastLogin($user['id']);
        if (Auth::isAdmin()) {
            $this->redirect('/admin');
        }
        if (Auth::gameRole() === 'OWNER') {
            $this->redirect('/ownership/request');
        }
        $this->redirect('/dashboard');
    }

    public function showRegister(): void {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/register');
    }

    public function register(): void {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        $accountType = $_POST['account_type'] ?? 'COACH';

        if (!$username || !$email || !$password) {
            $this->view('auth/register', ['error' => 'تمام فیلدها الزامی است']);
            return;
        }

        if ($password !== $confirm) {
            $this->view('auth/register', ['error' => 'رمز عبور و تکرار آن مطابقت ندارند']);
            return;
        }

        if (strlen($password) < 6) {
            $this->view('auth/register', ['error' => 'رمز عبور باید حداقل ۶ کاراکتر باشد']);
            return;
        }

        if ($this->userModel->findByEmail($email)) {
            $this->view('auth/register', ['error' => 'این ایمیل قبلاً ثبت شده است']);
            return;
        }

        if ($this->userModel->findByUsername($username)) {
            $this->view('auth/register', ['error' => 'این نام کاربری قبلاً استفاده شده است']);
            return;
        }

        $userId = $this->userModel->register([
            'username' => $username,
            'email'    => $email,
            'password' => $password,
            'role'     => 'manager',
            'game_role'=> $accountType === 'OWNER' ? 'OWNER' : 'COACH'
        ]);

        if (!$userId) {
            $this->view('auth/register', ['error' => 'خطا در ثبت‌نام، دوباره تلاش کنید']);
            return;
        }

        Auth::login((int)$userId);
        if (Auth::gameRole() === 'OWNER') {
            $this->redirect('/ownership/request');
        }
        $this->redirect('/club/select');
    }

    public function logout(): void {
        Auth::logout();
        $this->redirect('/login');
    }

    public function selectClub(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }
        $this->redirect('/manager/apply');
    }

    public function assignClub(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }
        $this->redirect('/manager/apply');
    }
}
