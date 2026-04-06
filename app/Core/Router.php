<?php
// app/Core/Router.php

class Router {
    private array $routes = [];
    private string $basePath;

    public function __construct(string $basePath = '') {
        $this->basePath = rtrim($basePath, '/');
    }

    public function get(string $path, string $handler): void {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, string $handler): void {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = str_replace($this->basePath, '', $uri);
        $uri = '/' . trim($uri, '/');

        $params = [];

        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = preg_replace('/\{([a-z_]+)\}/', '([^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                // استخراج نام پارامترها
                preg_match_all('/\{([a-z_]+)\}/', $route, $paramNames);
                array_shift($matches);
                $params = array_combine($paramNames[1], $matches) ?: [];

                [$controllerName, $action] = explode('@', $handler);
                $controllerFile = __DIR__ . '/../Controllers/' . $controllerName . '.php';

                if (!file_exists($controllerFile)) {
                    $this->abort(500, "Controller not found: $controllerName");
                    return;
                }

                require_once $controllerFile;
                $controller = new $controllerName();
                $controller->$action($params);
                return;
            }
        }

        $this->abort(404, 'صفحه مورد نظر یافت نشد');
    }

    private function abort(int $code, string $message): void {
        http_response_code($code);
        echo "<h1>$code</h1><p>$message</p>";
    }
}
