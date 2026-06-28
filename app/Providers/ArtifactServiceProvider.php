<?php
// app/Providers/ArtifactServiceProvider.php

namespace App\Providers;

use App\Services\Artifacts\ArtifactManager;
use App\Services\Artifacts\Contracts\ArtifactManagerInterface;
use Illuminate\Support\ServiceProvider;

class ArtifactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ArtifactManagerInterface::class, function ($app) {
            return new ArtifactManager();
        });
    }
}