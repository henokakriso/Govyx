<?php

declare(strict_types=1);

namespace Govyx\Core;

final class Router
{
    /**
     * [ 'method' => [ '/path/regex' => [Controller::class, 'method'] ] ]
     */
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, array $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, array $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, array $handler): void
    {
        $regex = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path) . '$#';
        $this->routes[$method][$regex] = $handler;
    }

    public function dispatch(): mixed
    {
        $method = Request::method();
        $path = Request::path();

        foreach ($this->routes[$method] ?? [] as $regex => $handler) {
            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$class, $methodName] = $handler;
                // Lazy instantiation: only the matched controller is constructed.
                return (new $class())->{$methodName}($params);
            }
        }
        Response::notFound('No route matches: ' . $method . ' ' . $path);
    }
}