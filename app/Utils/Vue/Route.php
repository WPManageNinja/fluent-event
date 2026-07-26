<?php

namespace FluentEvent\App\Utils\Vue;

use JsonSerializable;

class Route implements JsonSerializable
{
    use Enqueueable;
    
    /**
     * Route item.
     *
     * @var array
     */
    protected array $item;

    /**
     * Explicit menu visibility. Null until visible()/hidden() is called, at
     * which point it overrides the resolved default.
     *
     * @var bool|null
     */
    protected ?bool $visibleExplicit = null;

    /**
     * Whether this route was registered as a child of another route.
     * Children are hidden from the menu by default.
     *
     * @var bool
     */
    protected bool $isChild = false;

    /**
     * Route constructor.
     * 
     * @param string $path
     * @param string $component
     * @param string $name
     */
    public function __construct(string $path, string $component, string $name = '')
    {
        $this->item = [
            'name'      => $name,
            'path'      => $path,
            'component' => $component,
            'props'     => true,
            'children'  => [],
            'meta'      => [],
        ];
    }

    /**
     * Add meta data to the menu item.
     * 
     * @param  array|string $key
     * @param  mixed $value[description]
     * @return self
     */
    public function meta($key, $value = null)
    {
        if (is_array($key)) {
            $this->item['meta'] = array_merge($this->item['meta'], $key);
        } else {
            $this->item['meta'][$key] = $value;
        }

        return $this;
    }

    /**
     * Add middleware(s) to the menu item's meta.
     *
     * @param  string|array $middlewares
     * @return self
     */
    public function middleware($middlewares)
    {
        $middlewares = (array) $middlewares;

        if (!isset($this->item['meta']['middleware'])) {
            $this->item['meta']['middleware'] = [];
        }

        $this->item['meta']['middleware'] = array_merge(
            $this->item['meta']['middleware'],
            $middlewares
        );

        return $this;
    }

    /**
     * Set the icon for menu item.
     * 
     * @param  string $icon
     * @return self
     */
    public function icon($icon)
    {
        $this->item['meta']['icon'] = $icon;

        return $this;
    }

    /**
     * Set the label for menu item.
     * 
     * @param  string $label
     * @return self
     */
    public function label($label)
    {
        $this->item['label'] = $label;
        
        return $this;
    }

    /**
     * Set the label for menu item.
     * 
     * @param  string $label
     * @return self
     */
    public function title($label)
    {
        return $this->label($label);
    }

    /**
     * Show this item in the menu. This is the framework default for a
     * top-level item that has a title; call it explicitly to opt a child
     * (hidden by default) back into the menu.
     *
     * @param  bool $visible
     * @return self
     */
    public function visible(bool $visible = true)
    {
        $this->visibleExplicit = $visible;

        return $this;
    }

    /**
     * Hide this item from the menu while keeping the route navigable
     * (e.g. a detail screen reached via $router.push()).
     *
     * @param  bool $hidden
     * @return self
     */
    public function hidden(bool $hidden = true)
    {
        $this->visibleExplicit = !$hidden;

        return $this;
    }

    /**
     * Flag this route as a child of another route. Children are hidden from
     * the menu by default; call visible() to surface one.
     *
     * @return self
     */
    public function markAsChild()
    {
        $this->isChild = true;

        return $this;
    }

    /**
     * Resolve the effective menu visibility. Precedence:
     *   1. an explicit visible()/hidden() call,
     *   2. a legacy meta('visible', ...) value,
     *   3. hidden when the item has no title,
     *   4. hidden when the item is a child route,
     *   5. visible otherwise.
     *
     * @return bool
     */
    protected function resolveVisible()
    {
        if ($this->visibleExplicit !== null) {
            return $this->visibleExplicit;
        }

        if (array_key_exists('visible', $this->item['meta'])) {
            return (bool) $this->item['meta']['visible'];
        }

        if (empty($this->item['label'])) {
            return false;
        }

        if ($this->isChild) {
            return false;
        }

        return true;
    }

    /**
     * Set the name for menu item.
     * 
     * @param  string $name
     * @return self
     */
    public function name($name)
    {
        return $this->set('name', $name);
    }

    /**
     * Specify whether the menu item can receive props.
     * 
     * @param  bool $value
     * @return self
     */
    public function props($value = true)
    {
        return $this->set('props', $value);
    }

    /**
     * Specify that the menu item can't receive props.
     * 
     * @return self
     */
    public function noProps()
    {
        return $this->props(false);
    }

    /**
     * Add a child menu item.
     * 
     * @param  mixed ...$args  Arguments to pass to the Route constructor.
     * @return Route
     */
    public function child(...$args)
    {
        $child = (new Route(...$args))->markAsChild();

        if (!isset($this->item['children'])) {
            $this->item['children'] = [];
        }

        $this->item['children'][] = $child;

        return $child;
    }

    /**
     * Conditionally add a child route.
     *
     * @param mixed ...$args
     * @return Route|object
     */
    public function childIf(...$args)
    {
        $condition = array_pop($args);

        $shouldAdd = is_callable($condition) ? $condition() : (bool) $condition;

        if ($shouldAdd) {
            return $this->child(...$args);
        }

        // Return a dummy object that ignores method calls
        return new class {
            public function __call($name, $args) {
                return new self;
            }
        };
    }

    /**
     * Add children menu items.
     * 
     * @param  callable $callback
     * @return self
     */
    public function children($callback)
    {
        $children = new Children;

        $callback($children);

        $this->item['children'] = $children;

        return $this;
    }

    /**
     * Get one or all items of the route.
     * 
     * @param  string|null $key
     * @return mixed
     */
    public function get($key = null)
    {
        if ($key) {
            return isset($this->item[$key]) ? $this->item[$key] : null;
        }

        return $this->item;
    }

    /**
     * Set an item in the route.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function set(string $key, $value)
    {
        $this->item[$key] = $value;

        return $this;
    }

    /**
     * Clone the route (shallow copy).
     * 
     * @return self
     */
    public function copy()
    {
        $route = clone $this;

        return $route;
    }

    /**
     * Serialize the route item.
     * 
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $this->item['meta']['visible'] = $this->resolveVisible();

        return $this->item;
    }
}
