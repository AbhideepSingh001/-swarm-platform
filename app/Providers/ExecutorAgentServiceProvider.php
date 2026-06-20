<?php

namespace App\Providers;

use App\Agents\Executor\ExecutorAgent;
use App\Agents\Executor\Handlers\ApiHandler;
use App\Agents\Executor\Handlers\CommandHandler;
use App\Agents\Executor\Handlers\FileHandler;
use App\Agents\Executor\ResultReporter;
use Illuminate\Support\ServiceProvider;

class ExecutorAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExecutorAgent::class, function ($app) {
            return new ExecutorAgent(
                new ResultReporter(),
                new ApiHandler(),
                new CommandHandler(),
                new FileHandler(),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}