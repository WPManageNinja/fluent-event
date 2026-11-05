<?php

namespace FluentEvent\Tests\Helpers;

use ArrayObject;

/**
 * Stands in for the WPFluent router so app/Http/Routes/api.php can be read
 * without booting the framework. prefix() and withPolicy() return a new
 * recorder, as the real router does, sharing one collection.
 */
class RouteRecorder
{
    /** @var ArrayObject */
    private $collected;

    /** @var string */
    private $prefix;

    /** @var string|null */
    private $policy;

    public function __construct($prefix = '', $policy = null, ?ArrayObject $collected = null)
    {
        $this->prefix = $prefix;
        $this->policy = $policy;
        $this->collected = $collected ?: new ArrayObject();
    }

    public function prefix($prefix)
    {
        $combined = trim(trim($this->prefix, '/') . '/' . trim($prefix, '/'), '/');

        return new self($combined, $this->policy, $this->collected);
    }

    public function withPolicy($policy)
    {
        return new self($this->prefix, $policy, $this->collected);
    }

    public function withDefaultPolicy()
    {
        return $this->withPolicy('Policy');
    }

    public function group($callback)
    {
        $callback($this);

        return $this;
    }

    public function get($uri, $handler)
    {
        return $this->record('GET', $uri, $handler);
    }

    public function post($uri, $handler)
    {
        return $this->record('POST', $uri, $handler);
    }

    public function put($uri, $handler)
    {
        return $this->record('PUT', $uri, $handler);
    }

    public function patch($uri, $handler)
    {
        return $this->record('PATCH', $uri, $handler);
    }

    public function delete($uri, $handler)
    {
        return $this->record('DELETE', $uri, $handler);
    }

    public function int($param)
    {
        return $this->annotate('params', $param, 'int');
    }

    public function alphaNum($param)
    {
        return $this->annotate('params', $param, 'alphaNum');
    }

    public function alphaNumDash($param)
    {
        return $this->annotate('params', $param, 'alphaNumDash');
    }

    public function rateLimitPerMinute($limit)
    {
        return $this->annotate('meta', 'rate_limit_per_minute', $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all()
    {
        return $this->collected->getArrayCopy();
    }

    private function record($method, $uri, $handler)
    {
        $this->collected[] = [
            'method'  => $method,
            'uri'     => trim(trim($this->prefix, '/') . '/' . ltrim($uri, '/'), '/'),
            'handler' => $handler,
            'policy'  => $this->policy,
            'params'  => [],
            'meta'    => [],
        ];

        return $this;
    }

    // ->int('id') and friends attach to the last recorded route.
    private function annotate($bucket, $key, $value)
    {
        $routes = $this->collected->getArrayCopy();

        if ($routes) {
            $index = array_key_last($routes);
            $routes[$index][$bucket][$key] = $value;
            $this->collected->exchangeArray($routes);
        }

        return $this;
    }
}
