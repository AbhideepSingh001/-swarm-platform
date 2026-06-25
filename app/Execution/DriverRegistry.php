<?php

declare(strict_types=1);

namespace App\Execution;

use App\Contracts\ExecutionDriverInterface;
use Illuminate\Support\Collection;

class DriverRegistry
{
    /**
     * @var array<string, ExecutionDriverInterface>
     */
    private array $drivers = [];

    /**
     * Register a driver instance.
     */
    public function register(string $name, ExecutionDriverInterface $driver): self
    {
        $this->drivers[$name] = $driver;
        return $this;
    }

    /**
     * Get a driver by name. Throws if not found.
     */
    public function get(string $name): ExecutionDriverInterface
    {
        if (!isset($this->drivers[$name])) {
            throw new \InvalidArgumentException(
                "Execution driver [{$name}] not registered. Available: " . implode(', ', array_keys($this->drivers))
            );
        }

        return $this->drivers[$name];
    }

    /**
     * Check if a driver is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    /**
     * Get all registered drivers.
     *
     * @return array<string, ExecutionDriverInterface>
     */
    public function all(): array
    {
        return $this->drivers;
    }

    /**
     * Get all driver names.
     *
     * @return array<string>
     */
    public function names(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Get all drivers as a collection.
     */
    public function collect(): Collection
    {
        return collect($this->drivers);
    }

    /**
     * Unregister a driver.
     */
    public function unregister(string $name): self
    {
        unset($this->drivers[$name]);
        return $this;
    }
}