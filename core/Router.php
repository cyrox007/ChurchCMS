<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use InvalidArgumentException;
use RuntimeException;

final class Router
{
    private static ?self $instance = null;

    private array $routes = [];
    private array $globalMiddlewares = [];
    private array $preDispatchHandlers = [];
    private ?string $groupPrefix = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function addPreDispatchHandler(
        array $handler,
    ): self {
        if (count($handler) !== 2) {
            throw new InvalidArgumentException(
                'Pre-dispatch handler must be [class, method].'
            );
        }

        foreach ($this->preDispatchHandlers as $existing) {
            if ($existing === $handler) {
                return $this;
            }
        }

        $this->preDispatchHandlers[] = $handler;

        return $this;
    }

    public function addGlobalMiddleware(string $middleware): self
    {
        if (!in_array($middleware, $this->globalMiddlewares, true)) {
            $this->globalMiddlewares[] = $middleware;
        }
        return $this;
    }

    public function group(string $prefix): self
    {
        $this->groupPrefix = RouteTemplate::normalize($prefix);
        return $this;
    }

    public function endGroup(): self
    {
        $this->groupPrefix = null;
        return $this;
    }

    public function add(string $method, string $path, array $controller, array $middlewares = [], string $name = ''): self
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET','POST','PUT','PATCH','DELETE','OPTIONS','HEAD'], true)) {
            throw new InvalidArgumentException('Unsupported HTTP method.');
        }

        if (count($controller) !== 2) {
            throw new InvalidArgumentException('Controller must be [class, method].');
        }

        $path = $this->groupPrefix !== null
            ? RouteTemplate::normalize($this->groupPrefix . '/' . ltrim($path, '/'))
            : RouteTemplate::normalize($path);

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                throw new RuntimeException("Duplicate route {$method} {$path}");
            }
            if ($name !== '' && $route['name'] === $name) {
                throw new RuntimeException("Duplicate route name {$name}");
            }
        }

        $this->routes[] = compact('method','path','controller','middlewares','name');
        return $this;
    }

    public function url(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route['name'] !== $name) {
                continue;
            }

            $url = $route['path'];
            $used = [];

            $url = preg_replace_callback(
                '/\{([A-Za-z_][A-Za-z0-9_]*)(\*)?\}/',
                static function (array $matches) use ($params, &$used): string {
                    $key = $matches[1];
                    if (!array_key_exists($key, $params)) {
                        throw new RuntimeException(
                            "Missing route parameter: {$key}"
                        );
                    }

                    $used[$key] = true;
                    $value = (string) $params[$key];

                    if (($matches[2] ?? '') === '*') {
                        $segments = array_values(array_filter(
                            explode('/', trim($value, '/')),
                            static fn(string $segment): bool =>
                                $segment !== '',
                        ));

                        if ($segments === []) {
                            throw new RuntimeException(
                                "Empty wildcard route parameter: {$key}"
                            );
                        }

                        foreach ($segments as $segment) {
                            if (
                                $segment === '.'
                                || $segment === '..'
                            ) {
                                throw new RuntimeException(
                                    "Unsafe wildcard route parameter: {$key}"
                                );
                            }
                        }

                        return implode(
                            '/',
                            array_map('rawurlencode', $segments),
                        );
                    }

                    return rawurlencode($value);
                },
                $url,
            );

            if (!is_string($url)) {
                throw new RuntimeException("Unable to build route URL: {$name}");
            }

            $query = array_diff_key($params, $used);
            if ($query !== []) {
                $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            }

            return $url;
        }

        throw new RuntimeException("Unknown route name: {$name}");
    }

    public function dispatch(): never
    {
        $request = Request::fromGlobals();
        if ($request->hasInvalidJson()) {
            Response::json(['error' => $request->jsonError()], 400);
        }

        $requestPath = RouteTemplate::normalize($request->path());
        $method = $request->method();
        $allowed = [];

        foreach ($this->preDispatchHandlers as $handler) {
            [$class, $action] = $handler;
            $instance = new $class();

            if (!method_exists($instance, $action)) {
                throw new RuntimeException(
                    "Pre-dispatch handler not found: {$class}::{$action}"
                );
            }

            $instance->$action(
                $request,
                $requestPath,
            );
        }

        foreach ($this->routes as $route) {
            $pattern = RouteTemplate::compile($route['path']);
            if (preg_match($pattern, $requestPath, $matches) !== 1) {
                continue;
            }

            $allowed[$route['method']] = true;
            if ($route['method'] !== $method) {
                continue;
            }

            foreach (array_merge($this->globalMiddlewares, $route['middlewares']) as $middleware) {
                $instance = new $middleware();
                if (!$instance->handle($request)) {
                    exit;
                }
            }

            [$class, $action] = $route['controller'];
            $instance = new $class();
            if (!method_exists($instance, $action)) {
                throw new RuntimeException("Controller action not found: {$class}::{$action}");
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[] = rawurldecode($value);
                }
            }

            $instance->$action($request, ...$params);
            exit;
        }

        if ($allowed !== []) {
            header('Allow: ' . implode(', ', array_keys($allowed)));
            Response::text('405 Method Not Allowed', 405);
        }

        Response::text('404 Not Found', 404);
    }
}
