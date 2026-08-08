<?php

namespace App\Platform\Core\Hooks;

use App\Platform\Core\Logs\PlatformLogManager;
use App\Platform\Core\Registry\PlatformRegistry;

class HookManager
{
    /**
     * @var array<string, array<int, array<string, HookCallback>>>
     */
    private array $actions = [];

    /**
     * @var array<string, array<int, array<string, HookCallback>>>
     */
    private array $filters = [];

    public function __construct(
        private readonly HookExceptionHandler $exceptions,
        private readonly PlatformRegistry $registry,
        private readonly PlatformLogManager $logs,
    ) {
        //
    }

    public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        if (! $this->registry->hookIsRegistered($hook)) {
            $this->logs->error('Blocked unregistered action hook registration.', ['hook' => $hook]);

            return;
        }

        $this->add($this->actions, $hook, $callback, $priority, $acceptedArgs);
    }

    public function doAction(string $hook, mixed ...$args): void
    {
        if (! $this->registry->hookIsRegistered($hook)) {
            $this->logs->error('Blocked unregistered action hook execution.', ['hook' => $hook]);

            return;
        }

        foreach ($this->callbacks($this->actions, $hook) as $callback) {
            try {
                call_user_func_array($callback->callback, array_slice($args, 0, $callback->acceptedArgs));
            } catch (\Throwable $exception) {
                $this->exceptions->callbackFailed('action', $hook, $callback, $exception);
            }
        }
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        if (! $this->registry->hookIsRegistered($hook)) {
            $this->logs->error('Blocked unregistered filter hook registration.', ['hook' => $hook]);

            return;
        }

        $this->add($this->filters, $hook, $callback, $priority, $acceptedArgs);
    }

    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (! $this->registry->hookIsRegistered($hook)) {
            $this->logs->error('Blocked unregistered filter hook execution.', ['hook' => $hook]);

            return $value;
        }

        foreach ($this->callbacks($this->filters, $hook) as $callback) {
            try {
                $arguments = array_slice([$value, ...$args], 0, $callback->acceptedArgs);
                $value = call_user_func_array($callback->callback, $arguments);
            } catch (\Throwable $exception) {
                $this->exceptions->callbackFailed('filter', $hook, $callback, $exception);
            }
        }

        return $value;
    }

    public function removeAction(string $hook, callable|string $callback): void
    {
        $this->remove($this->actions, $hook, $callback);
    }

    public function removeFilter(string $hook, callable|string $callback): void
    {
        $this->remove($this->filters, $hook, $callback);
    }

    public function hasAction(string $hook): bool
    {
        return $this->callbacks($this->actions, $hook) !== [];
    }

    public function hasFilter(string $hook): bool
    {
        return $this->callbacks($this->filters, $hook) !== [];
    }

    public function reset(): void
    {
        $this->actions = [];
        $this->filters = [];
    }

    /**
     * @param array<string, array<int, array<string, HookCallback>>> $registry
     */
    private function add(array &$registry, string $hook, callable $callback, int $priority, int $acceptedArgs): void
    {
        $priority = max(0, $priority);
        $acceptedArgs = max(0, $acceptedArgs);
        $id = $this->callbackId($callback);

        $registry[$hook][$priority][$id] = new HookCallback($id, $callback, $priority, $acceptedArgs);
    }

    /**
     * @param array<string, array<int, array<string, HookCallback>>> $registry
     */
    private function remove(array &$registry, string $hook, callable|string $callback): void
    {
        if (! isset($registry[$hook])) {
            return;
        }

        $id = is_string($callback) ? $callback : $this->callbackId($callback);

        foreach ($registry[$hook] as $priority => $callbacks) {
            unset($registry[$hook][$priority][$id]);

            if ($registry[$hook][$priority] === []) {
                unset($registry[$hook][$priority]);
            }
        }

        if ($registry[$hook] === []) {
            unset($registry[$hook]);
        }
    }

    /**
     * @param array<string, array<int, array<string, HookCallback>>> $registry
     * @return array<int, HookCallback>
     */
    private function callbacks(array $registry, string $hook): array
    {
        if (! isset($registry[$hook])) {
            return [];
        }

        ksort($registry[$hook]);

        $callbacks = [];

        foreach ($registry[$hook] as $priorityCallbacks) {
            foreach ($priorityCallbacks as $callback) {
                $callbacks[] = $callback;
            }
        }

        return $callbacks;
    }

    private function callbackId(callable $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if ($callback instanceof \Closure) {
            return spl_object_hash($callback);
        }

        if (is_array($callback)) {
            $target = is_object($callback[0]) ? $callback[0]::class : (string) $callback[0];

            return $target.'::'.(string) $callback[1];
        }

        if (is_object($callback) && method_exists($callback, '__invoke')) {
            return $callback::class.'::__invoke';
        }

        return md5(serialize($callback));
    }
}
