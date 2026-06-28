<?php
// app/Providers/MetricsServiceProvider.php

namespace App\Providers;

use App\Services\Metrics\Aggregators\DriverAggregator;
use App\Services\Metrics\Aggregators\WorkflowAggregator;
use App\Services\Metrics\Contracts\MetricsCollectorInterface;
use App\Services\Metrics\MetricsCollector;
use Illuminate\Support\ServiceProvider;

class MetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetricsCollectorInterface::class, function ($app) {
            return new MetricsCollector(
                new DriverAggregator(),
                new WorkflowAggregator(),
            );
        });
    }
}