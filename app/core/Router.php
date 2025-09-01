<?php
// File: app/core/Router.php
declare(strict_types=1);

class Router
{
    private array $routes = [];
    private string $basePath;

    public function __construct(string $basePath = '')
    {
        // Normalize basePath, e.g. "/Inventory_Optimization_Web_App"
        $this->basePath = rtrim($basePath, '/');
    }

    public function add(string $method, string $path, $handler): void
    {
        $method = strtoupper($method);
        $this->routes[$method][$path] = $handler;
    }

    public function dispatch(string $uri, string $method): void
    {
        $method = strtoupper($method);

        // Strip query string
        $uri = parse_url($uri, PHP_URL_PATH);

        // Remove basePath
        if ($this->basePath && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }

        if ($uri === '' || $uri === false) {
            $uri = '/';
        }

        if (isset($this->routes[$method][$uri])) {
            $handler = $this->routes[$method][$uri];
            if (is_callable($handler)) {
                $handler();
            } elseif (is_string($handler)) {
                [$class, $action] = explode('@', $handler);
                (new $class())->$action();
            } else {
                throw new Exception("Invalid route handler for $uri");
            }
        } else {
            http_response_code(404);
            echo "<h1>404 Not Found</h1><p>No route for $method $uri</p>";
        }
    }
}
