<?php

namespace App\Providers;

use App\Contracts\Results\ResultStoreInterface;
use App\Services\Analytics\DriverAggregator;
use App\Services\Analytics\MetricsCollector;
use App\Services\Analytics\WorkflowAggregator;
use App\Services\Results\ArtifactManager;
use App\Services\Results\Drivers\DatabaseResultDriver;
use App\Services\Results\ResultStore;
use Illuminate\Support\ServiceProvider;

class ResultServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResultStoreInterface::class, DatabaseResultDriver::class);

        $this->app->singleton(ResultStore::class, function ($app) {
            return new ResultStore($app->make(ResultStoreInterface::class));
        });

        $this->app->singleton(ArtifactManager::class, function ($app) {
            return new ArtifactManager(config('swarm.artifacts.disk', 'local'));
        });

        $this->app->singleton(MetricsCollector::class, function ($app) {
            return new MetricsCollector(
                $app->make(DriverAggregator::class),
                $app->make(WorkflowAggregator::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}