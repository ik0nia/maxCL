<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<int, array{pattern:string, handler:callable, middleware:array<int, callable>}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes['GET'][] = compact('pattern', 'handler', 'middleware');
    }

    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes['POST'][] = compact('pattern', 'handler', 'middleware');
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $method = strtoupper($method);

        foreach (($this->routes[$method] ?? []) as $route) {
            $regex = $this->toRegex($route['pattern'], $paramNames);
            if (!preg_match($regex, $path, $m)) {
                continue;
            }

            $params = [];
            foreach ($paramNames as $name) {
                $params[$name] = $m[$name] ?? null;
            }

            foreach ($route['middleware'] as $mw) {
                $mw($method, $path, $params);
            }

            ($route['handler'])($params);
            return;
        }

        http_response_code(404);
        echo View::render('errors/404', ['title' => 'Pagina nu a fost găsită']);
    }

    /**
     * @param-out array<int, string> $paramNames
     */
    private function toRegex(string $pattern, ?array &$paramNames = null): string
    {
        $paramNames = [];
        $re = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $pattern);

        return '#^' . $re . '$#';
    }
}

