<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('workflow.{executionId}', function ($user, int $executionId) {
    // In production, add ownership/permission checks here
    return true;
});

Broadcast::channel('workflows', function ($user) {
    // In production, add admin/permission checks here
    return true;
});