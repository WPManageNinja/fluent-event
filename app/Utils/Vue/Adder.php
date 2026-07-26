<?php

namespace FluentEvent\App\Utils\Vue;

class Adder
{
    /**
     * @var array|Submenu
     */
    protected $group = null;

    /**
     * @param array|Submenu $group
     */
    public function __construct(&$group)
    {
        $this->group = &$group;
    }

    /**
     * @param string $id
     * @return self|null
     */
    public function submenu(string $id): ?self
    {
        $items = &$this->group;

        if ($this->group instanceof Submenu) {
            $items = &$this->group->getItems();
        }

        foreach ($items as &$item) {
            if ($item instanceof Route) continue;
            if (isset($item['submenu']) && $item['id'] === $id) {
                return new self($item['submenu'][0]);
            }
        }

        return null;
    }

    /**
     * Add a new route to the submenu.
     * 
     * @param mixed $args
     * @return Route
     */
    public function add(...$args)
    {
        $route = new Route(...$args);

        if ($this->group instanceof Submenu) {
        	return $this->group->addOrReplace($route);
        }

        return $this->addOrReplace($route);
    }

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
     * Add a new route or replace if exists in the submenu.
     * 
     * @param  Route $newRoute
     * @return Route
     */
    public function addOrReplace($newRoute)
    {
        foreach ($this->group as $index => $route) {
            if (
                $route instanceof Route &&
                $newRoute instanceof Route &&
                $route->get('path') === $newRoute->get('path')
            ) {
                $this->group[$index] = $newRoute;
                return $newRoute;
            }
        }

        $this->group[] = $newRoute;

        return $newRoute;
    }
}
