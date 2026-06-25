<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AgentLoadBalancer;
use App\Services\TaskOrchestrationService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgentLoadBalancer::class, function () {
            return new AgentLoadBalancer();
        });

        $this->app->singleton(TaskOrchestrationService::class, function ($app) {
            return new TaskOrchestrationService($app->make(AgentLoadBalancer::class));
        });
    }

    public function boot(): void
    {
        //
    }
}