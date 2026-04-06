<?php
// app/Core/Controller.php

class Controller {
    public function __construct() {
        // base constructor
    }

    protected function view(string $view, array $data = []): void {
        extract($data);
        $viewFile = __DIR__ . '/../Views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewFile)) {
            die("View not found: $view");
        }

        require $viewFile;
    }

    protected function json(mixed $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function redirect(string $url): void {
        header("Location: $url");
        exit;
    }

    protected function back(): void {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    protected function input(string $key, mixed $default = null): mixed {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function validate(array $rules): array {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $value = $this->input($field);
            foreach (explode('|', $rule) as $r) {
                if ($r === 'required' && empty($value)) {
                    $errors[$field] = "فیلد $field الزامی است";
                } elseif (str_starts_with($r, 'min:')) {
                    $min = (int) substr($r, 4);
                    if (strlen($value) < $min) {
                        $errors[$field] = "حداقل $min کاراکتر";
                    }
                } elseif ($r === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "ایمیل معتبر نیست";
                }
            }
        }
        return $errors;
    }

    protected function requireAuth(): void {
        if (!Auth::check()) {
            $this->redirect('/login');
        }
    }

    protected function requireAdmin(): void {
        if (!Auth::isAdmin()) {
            $this->redirect('/dashboard');
        }
    }
}
