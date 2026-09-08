<?php
declare(strict_types=1);

namespace App;

/**
 * Minimal router. Exact-match paths only — enough for most apps and
 * trivial for AI to extend. Add path params here later if you need them.
 */
final class Router
{
    /** @var array<string, array<string, callable|array>> */
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/') ?: '/';
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not Found', 'path' => $path]);
            return;
        }

        if (is_array($handler)) {
            [$class, $action] = $handler;
            $handler = [new $class(), $action];
        }

        $handler();
    }
}
