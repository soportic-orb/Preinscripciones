<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router propio con soporte de parámetros {param}, grupos de middleware y nombres.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:mixed,middleware:array<int,string>,name:?string}> */
    private array $routes = [];
    /** @var array<int,string> pila de middleware del grupo actual */
    private array $groupMiddleware = [];

    public function get(string $pattern, mixed $handler): Route
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): Route
    {
        return $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): Route
    {
        return $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): Route
    {
        return $this->add('DELETE', $pattern, $handler);
    }

    /** @param array<int,string> $middleware */
    public function group(array $middleware, callable $callback): void
    {
        $previous = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($previous, $middleware);
        $callback($this);
        $this->groupMiddleware = $previous;
    }

    private function add(string $method, string $pattern, mixed $handler): Route
    {
        $pattern = '/' . trim($pattern, '/');
        $regex = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        $index = count($this->routes);
        $this->routes[$index] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $this->groupMiddleware,
            'name' => null,
        ];
        return new Route($this, $index);
    }

    public function setName(int $index, string $name): void
    {
        if (isset($this->routes[$index])) {
            $this->routes[$index]['name'] = $name;
        }
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $request->setRouteParams($params);

            // Ejecutar middleware en orden.
            foreach ($route['middleware'] as $mw) {
                Middleware::run($mw, $request);
            }

            $this->invoke($route['handler'], $request);
            return;
        }

        if ($allowed !== []) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            Response::html('<h1>405 — Método no permitido</h1>', 405);
        }

        Response::html(View::render('errors/404', [], 'layouts/app'), 404);
    }

    private function invoke(mixed $handler, Request $request): void
    {
        if (is_callable($handler)) {
            $handler($request);
            return;
        }
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $action] = explode('@', $handler, 2);
            $fqcn = 'App\\Controllers\\' . $class;
            $controller = new $fqcn();
            $controller->$action($request);
            return;
        }
        throw new \RuntimeException('Handler de ruta no válido.');
    }
}
