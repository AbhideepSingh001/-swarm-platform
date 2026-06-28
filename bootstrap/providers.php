<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\ExecutorAgentServiceProvider::class,
    App\Providers\ExecutionServiceProvider::class,
    App\Providers\ResultStoreServiceProvider::class,
    App\Providers\ArtifactServiceProvider::class,
    App\Providers\MetricsServiceProvider::class,
];
