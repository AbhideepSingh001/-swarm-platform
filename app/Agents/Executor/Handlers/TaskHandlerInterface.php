<?php

namespace App\Agents\Executor\Handlers;

use App\Agents\Executor\ExecutionResult;
use App\Agents\Executor\ExecutionTask;

interface TaskHandlerInterface
{
    public function canHandle(string $type): bool;
    public function execute(ExecutionTask $task): ExecutionResult;
    public function getName(): string;
}