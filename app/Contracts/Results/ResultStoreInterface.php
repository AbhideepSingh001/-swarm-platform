<?php

namespace App\Contracts\Results;

use App\Models\TaskResult;

interface ResultStoreInterface
{
    public function create(array $data): TaskResult;

    public function update(TaskResult $result, array $data): TaskResult;

    public function find(int $id): ?TaskResult;

    public function findByTask(int $taskId): ?TaskResult;

    public function query(array $filters = []);
}