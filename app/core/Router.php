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
        $bp = trim($basePath);
        if ($bp === '') {
            $this->basePath = '';
        } else {
            // ensure it starts with a single leading slash and no trailing slash
            $bp = '/' . ltrim($bp, '/');
            $this->basePath = rtrim($bp, '/');
        }
    }

    /**
     * Add a route.
     * Small normalization of the provided path so "/login/" and "/login" map to the same route.
     */
    public function add(string $method, string $path, $handler): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);
        $this->routes[$method][$path] = $handler;
    }

    /**
     * Dispatch the incoming request URI to the registered route handlers.
     */
    public function dispatch(string $uri, string $method): void
    {
        $method = strtoupper($method);

        // Keep original for debugging / messages if needed
        $originalUri = $uri;

        // Strip query string and decode
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === false || $path === null) {
            $path = '';
        }
        $path = rawurldecode((string)$path);

        // Normalize any multiple slashes
        $path = preg_replace('#/+#', '/', $path);

        // Remove basePath if present (example: /Inventory_Optimization_Web_App)
        if ($this->basePath !== '') {
            // Remove when path equals the basePath or when path starts with basePath + '/'
            if ($path === $this->basePath) {
                $path = '/';
            } elseif (str_starts_with($path, $this->basePath . '/')) {
                $path = substr($path, strlen($this->basePath));
                if ($path === '') {
                    $path = '/';
                }
            }
        }

        // Defensive: strip a leading "/public" segment if present (hosts sometimes expose /public in URL)
        if (str_starts_with($path, '/public/')) {
            $path = substr($path, 7); // remove "/public"
            if ($path === '' || $path === false) {
                $path = '/';
            }
        } elseif ($path === '/public') {
            $path = '/';
        }

        // Normalize trailing slash (keep "/" as root)
        if ($path === '' || $path === false) {
            $path = '/';
        } elseif ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Final normalization to ensure a single leading slash
        if ($path === '') {
            $path = '/';
        } elseif (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Try exact match first
        if (isset($this->routes[$method][$path])) {
            $handler = $this->routes[$method][$path];
            $this->executeHandler($handler, $path);
            return;
        }

        // If not found, also attempt to match with a trailing slash variant (in case routes were added with/without slash)
        $altPath = ($path === '/' ? '/' : ($path . '/'));
        if (isset($this->routes[$method][$altPath])) {
            $handler = $this->routes[$method][$altPath];
            $this->executeHandler($handler, $altPath);
            return;
        }

        // Not found
        http_response_code(404);
        // Show normalized path in message (helps debugging); also include original for clarity.
        echo "<h1>404 Not Found</h1><p>No route for {$method} {$path}</p>";
    }

    /**
     * Normalize a route path when registering routes.
     * Ensures leading slash, removes duplicate slashes and trims trailing slash (except for root).
     */
    private function normalizePath(string $path): string
    {
        $p = parse_url($path, PHP_URL_PATH);
        if ($p === false || $p === null) {
            $p = '';
        }
        $p = rawurldecode((string)$p);
        $p = preg_replace('#/+#', '/', $p);

        if ($p === '') {
            $p = '/';
        } elseif (!str_starts_with($p, '/')) {
            $p = '/' . $p;
        }

        if ($p !== '/' && str_ends_with($p, '/')) {
            $p = rtrim($p, '/');
        }

        return $p;
    }

    /**
     * Execute a route handler which may be a callable or a "Class@method" string.
     */
    private function executeHandler($handler, string $path): void
    {
        if (is_callable($handler)) {
            $handler();
            return;
        }

        if (is_string($handler)) {
            if (strpos($handler, '@') === false) {
                throw new Exception("Invalid route handler string for {$path}. Expected 'Class@method'.");
            }
            [$class, $action] = explode('@', $handler, 2);
            if (!class_exists($class)) {
                throw new Exception("Controller class {$class} not found for route {$path}.");
            }
            $controller = new $class();
            if (!method_exists($controller, $action)) {
                throw new Exception("Action {$action} not found on controller {$class} for route {$path}.");
            }
            $controller->$action();
            return;
        }

        throw new Exception("Invalid route handler for {$path}");
    }
}
