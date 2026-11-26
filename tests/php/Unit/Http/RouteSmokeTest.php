<?php

namespace FluentEvent\Tests\Unit\Http;

use FluentEvent\App\Http\Policies\Policy;
use FluentEvent\Tests\Helpers\RouteRecorder;
use FluentEvent\Tests\TestCase;

/**
 * Loads app/Http/Routes/api.php and checks every endpoint resolves, is policied,
 * and that no write on a public-facing policy relies on its verifyRequest() fallback (routes.md).
 */
class RouteSmokeTest extends TestCase
{
    const CONTROLLER_NS = 'FluentEvent\\App\\Http\\Controllers\\';
    const POLICY_NS = 'FluentEvent\\App\\Http\\Policies\\';

    /** @var array<int, array<string, mixed>> */
    private static $routes;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $router = new RouteRecorder();

        require FLUENT_EVENT_PLUGIN_DIR . 'app/Http/Routes/api.php';

        self::$routes = $router->all();
    }

    public function test_the_route_file_declares_the_endpoints_the_admin_app_calls()
    {
        // A floor, so adding an endpoint needs no edit here.
        $this->assertGreaterThanOrEqual(25, count(self::$routes));
    }

    public function test_every_route_points_at_a_controller_method_that_exists()
    {
        foreach (self::$routes as $route) {
            [$class, $method] = $this->handler($route);

            $this->assertTrue(class_exists($class), "{$this->label($route)} points at missing controller {$class}");
            $this->assertTrue(method_exists($class, $method), "{$this->label($route)} points at missing {$class}@{$method}");
        }
    }

    public function test_every_route_handler_is_public_and_not_static()
    {
        foreach (self::$routes as $route) {
            [$class, $method] = $this->handler($route);

            $reflection = new \ReflectionMethod($class, $method);

            $this->assertTrue($reflection->isPublic(), "{$class}@{$method} is routed but not public");
            $this->assertFalse($reflection->isStatic(), "{$class}@{$method} is routed but static; the router instantiates it");
        }
    }

    public function test_every_route_carries_a_policy_that_exists()
    {
        foreach (self::$routes as $route) {
            $this->assertNotEmpty($route['policy'], "{$this->label($route)} is outside any withPolicy() group");

            $class = self::POLICY_NS . $route['policy'];

            $this->assertTrue(class_exists($class), "{$route['policy']} is referenced by a route but does not exist");
            $this->assertTrue($class === Policy::class || is_subclass_of($class, Policy::class), "{$route['policy']} does not extend the plugin's base Policy");
        }
    }

    public function test_every_write_route_has_its_own_policy_method()
    {
        // Without one the framework falls back to verifyRequest(). The base Policy's is manage_options,
        // the admin routes' only check; a public-facing policy's is looser, so its writes name their own.
        foreach (self::$routes as $route) {
            if ($route['method'] === 'GET' || $route['policy'] === 'Policy') {
                continue;
            }

            [, $action] = $this->handler($route);
            $policy = self::POLICY_NS . $route['policy'];

            $this->assertTrue(
                $this->declaresOwnMethod($policy, $action),
                "{$this->label($route)} falls back to {$route['policy']}::verifyRequest(); add {$route['policy']}::{$action}()"
            );
        }
    }

    public function test_every_id_placeholder_is_constrained_to_an_integer()
    {
        foreach (self::$routes as $route) {
            if (!preg_match_all('/\{([a-z_]+)\}/i', $route['uri'], $matches)) {
                continue;
            }

            foreach ($matches[1] as $placeholder) {
                if (!preg_match('/id$/i', $placeholder)) {
                    continue;
                }

                $this->assertSame(
                    'int',
                    $route['params'][$placeholder] ?? null,
                    "{$this->label($route)} does not constrain {{$placeholder}} with ->int()"
                );
            }
        }
    }

    // Registration is rate-limited in the controller (RateLimiter::isSpamming). Before-middleware runs in the
    // permission callback, which WordPress runs again for the Allow header, so a count there is taken twice.
    public function test_no_registration_endpoint_counts_in_the_permission_check()
    {
        foreach (['community/events/{id}/register', 'community/events/{id}/register-guest', 'portal/events/{id}/register-guest'] as $uri) {
            $route = $this->find('POST', $uri);

            $this->assertNotNull($route, "POST {$uri} is gone");
            $this->assertEmpty($route['meta']['before'] ?? [], "POST {$uri} runs before-middleware");
        }
    }

    public function test_no_two_routes_share_a_verb_and_uri()
    {
        $labels = array_map([$this, 'label'], self::$routes);

        $this->assertSame(array_unique($labels), $labels);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function handler(array $route)
    {
        $handler = $route['handler'];

        if (is_array($handler)) {
            return [$handler[0], $handler[1]];
        }

        [$controller, $method] = explode('@', $handler, 2);

        return [self::CONTROLLER_NS . $controller, $method];
    }

    private function declaresOwnMethod($class, $method)
    {
        if (!method_exists($class, $method)) {
            return false;
        }

        $declaringClass = (new \ReflectionMethod($class, $method))->getDeclaringClass()->getName();

        return $declaringClass !== Policy::class;
    }

    private function find($method, $uri)
    {
        foreach (self::$routes as $route) {
            if ($route['method'] === $method && $route['uri'] === $uri) {
                return $route;
            }
        }

        return null;
    }

    private function label(array $route)
    {
        return $route['method'] . ' /' . $route['uri'];
    }
}
