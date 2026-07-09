<?php

namespace FluentEvents\App\Utils\Vue;

use Exception;

/**
 * @property Router $router
 */
trait CommonTrait
{
    /**
     * Conditionally add a new route to the submenu.
     *
     * @param mixed ...$args Route constructor arguments.
     * @return Route|object
     */
    public function addIf(...$args)
    {
        $condition = array_pop($args);

        $shouldAdd = is_callable($condition) ? $condition() : (bool) $condition;

        if ($shouldAdd) {
            return $this->add(...$args);
        }

        // If false, allow method chaining without breaking
        return new class {
            /**
             * @param string $name
             * @param array<mixed> $args
             * @return self
             */
            public function __call(string $name, array $args): self {
                return new self;
            }
        };
    }

	/**
     * Find a route by name and use it.
     *
     * @param  string $name
     * @return Route
     * @throws Exception
     */
    public function use(string $name)
    {
    	if (method_exists($this, 'getRoutes')) {
        	$allRoutes = $this->getRoutes();
    	} else {
    		$allRoutes = $this->router->getRoutes();
    	}

        foreach ($allRoutes as $group => $routes) {
            $found = $this->findRouteByName($routes, $name);
            
            if ($found) {
                $this->routes[] = $clone = $found->copy();

                return $clone;
            }
        }

        throw new Exception(esc_html("Route with name '{$name}' not found."));
    }

    /**
     * Conditionally find a route by name and use it.
     * 
     * @param string $name
     * @param mixed  $condition Boolean or callable returning boolean
     * @return Route|object
     * @throws Exception
     */
    public function useIf(string $name, $condition)
    {
        $shouldUse = is_callable($condition) ? $condition() : (bool) $condition;

        if (!$shouldUse) {
            // Return dummy object for fluent no-op chaining
            return new class {
                /**
                 * @param string $method
                 * @param array<mixed> $args
                 * @return self
                 */
                public function __call(string $method, array $args): self {
                    return $this;
                }
                /**
                 * @param string $name
                 * @return self
                 */
                public function __get(string $name): self {
                    return $this;
                }
            };
        }

        return $this->use($name);
    }

    /**
     * Find a route by name.
     *
     * @param  array $routes
     * @param  string $name
     * @return Route|null
     */
    public function findRouteByName(array $routes, string $name): ?Route
    {
        foreach ($routes as $route) {
            if ($route instanceof Route && $route->get('name') === $name) {
                return $route;
            }

            if (is_array($route) && isset($route['submenu'])) {
                foreach ($route['submenu'] as $submenu) {
                    $submenu = is_array($submenu) ? $submenu : $submenu->getItems();
                    $found = $this->findRouteByName($submenu, $name);
                    if ($found) {
                        return $found;
                    }
                }
            } else {
                if (!empty($children = $route->get('children'))) {
                    $children = is_array($children) ? $children : $children->getItems();
                    $found = $this->findRouteByName($children, $name);
                    if ($found) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Remove route by path (deduplication).
     * 
     * @param  array  $routes
     * @param  string $path
     * @return array
     */
    protected function removeRouteByPath($routes, $path)
    {
        $routes = array_filter($routes, function ($route) use ($path) {
            if ($route instanceof Route) {
                return $route->get('path') !== $path;
            }

            if (is_array($route) && isset($route['path'])) {
                return $route['path'] !== $path;
            }
            
            return true;
        });

        return array_values($routes);
    }
}
