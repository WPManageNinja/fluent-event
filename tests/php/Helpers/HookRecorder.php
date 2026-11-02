<?php

namespace FluentEvent\Tests\Helpers;

/**
 * Stands in for the WPFluent app while app/Hooks/*.php run, recording every
 * registration. Plain add_action()/add_filter() calls are routed here by the test.
 */
class HookRecorder
{
    // config/app.php hook_prefix; the framework concatenates it with no separator.
    const HOOK_PREFIX = 'fluent-event';

    /** @var array<int, array{type: string, hook: string, callback: mixed, priority: int}> */
    public $registrations = [];

    public function addAction($hook, $callback, $priority = 10, $acceptedArgs = 1)
    {
        return $this->record('action', $hook, $callback, $priority);
    }

    public function addCustomAction($hook, $callback, $priority = 10, $acceptedArgs = 1)
    {
        return $this->record('action', self::HOOK_PREFIX . $hook, $callback, $priority);
    }

    public function addFilter($hook, $callback, $priority = 10, $acceptedArgs = 1)
    {
        return $this->record('filter', $hook, $callback, $priority);
    }

    public function addCustomFilter($hook, $callback, $priority = 10, $acceptedArgs = 1)
    {
        return $this->record('filter', self::HOOK_PREFIX . $hook, $callback, $priority);
    }

    // The real app defers these to init; running them now records what they add.
    public function ready(callable $callback)
    {
        $callback();
    }

    /**
     * @return array<int, array{type: string, hook: string, callback: mixed, priority: int}>
     */
    public function all($type = null)
    {
        if ($type === null) {
            return $this->registrations;
        }

        return array_values(array_filter($this->registrations, function ($entry) use ($type) {
            return $entry['type'] === $type;
        }));
    }

    /**
     * @return array<int, string>
     */
    public function hooks($type = null)
    {
        return array_column($this->all($type), 'hook');
    }

    /**
     * @return array<int, array{type: string, hook: string, callback: mixed, priority: int}>
     */
    public function on($hook)
    {
        return array_values(array_filter($this->registrations, function ($entry) use ($hook) {
            return $entry['hook'] === $hook;
        }));
    }

    private function record($type, $hook, $callback, $priority)
    {
        $this->registrations[] = [
            'type'     => $type,
            'hook'     => $hook,
            'callback' => $callback,
            'priority' => (int) $priority,
        ];

        return $this;
    }
}
