<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AgentLoadBalancer;
use App\Services\TaskOrchestrationService;
use App\Services\Swarm\AsyncSwarmRunner;
use App\Services\Swarm\WorkflowBatchMonitor;
use App\Services\Swarm\BatchBroadcastService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Day 16
        $this->app->singleton(AgentLoadBalancer::class, function () {
            return new AgentLoadBalancer();
        });

        $this->app->singleton(TaskOrchestrationService::class, function ($app) {
            return new TaskOrchestrationService($app->make(AgentLoadBalancer::class));
        });

        // Day 18
        $this->app->singleton(BatchBroadcastService::class, function () {
            return new BatchBroadcastService();
        });

        $this->app->singleton(WorkflowBatchMonitor::class, function ($app) {
            return new WorkflowBatchMonitor(
                $app->make(BatchBroadcastService::class)
            );
        });

        $this->app->singleton(AsyncSwarmRunner::class, function ($app) {
            return new AsyncSwarmRunner(
                $app->make(WorkflowBatchMonitor::class)
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
