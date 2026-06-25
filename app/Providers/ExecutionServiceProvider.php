<?php

declare(strict_types=1);

namespace App\Providers;

use App\Execution\DriverRegistry;
use App\Execution\Drivers\CodeDriver;
use App\Execution\Drivers\HTTPDriver;
use App\Execution\Drivers\LLMDriver;
use App\Execution\Drivers\ShellDriver;
use App\Execution\Engine;
use App\Execution\RetryManager;
use App\Execution\Sandbox;
use Illuminate\Support\ServiceProvider;

class ExecutionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DriverRegistry::class, function ($app) {
            $registry = new DriverRegistry();

            $registry
                ->register('code', new CodeDriver(config('execution.drivers.code', [])))
                ->register('llm', new LLMDriver(config('execution.drivers.llm', [])))
                ->register('shell', new ShellDriver(config('execution.drivers.shell', [])))
                ->register('http', new HTTPDriver(config('execution.drivers.http', [])));

            return $registry;
        });

        $this->app->singleton(RetryManager::class, function ($app) {
            return new RetryManager();
        });

        $this->app->singleton(Sandbox::class, function ($app) {
            return new Sandbox(config('execution.sandbox', []));
        });

        $this->app->singleton(Engine::class, function ($app) {
            return new Engine(
                registry: $app->make(DriverRegistry::class),
                retryManager: $app->make(RetryManager::class),
                sandbox: $app->make(Sandbox::class)
            );
        });
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/execution.php' => config_path('execution.php'),
        ], 'execution-config');
    }
}