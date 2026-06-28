<?php
// app/Providers/ResultStoreServiceProvider.php

namespace App\Providers;

use App\Services\Results\Contracts\ResultStoreInterface;
use App\Services\Results\Drivers\DatabaseResultDriver;
use Illuminate\Support\ServiceProvider;

class ResultStoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResultStoreInterface::class, function ($app) {
            $driver = config('swarm.result_store.driver', 'database');

            return match ($driver) {
                'database' => new DatabaseResultDriver(),
                default => new DatabaseResultDriver(),
            };
        });
    }
}