<?php

namespace App\Agents\Executor\Handlers;

use App\Agents\Executor\ExecutionResult;
use App\Agents\Executor\ExecutionTask;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class FileHandler implements TaskHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return $type === 'file';
    }

    public function getName(): string
    {
        return 'file';
    }

    public function execute(ExecutionTask $task): ExecutionResult
    {
        $start = microtime(true);
        $config = $task->config;

        try {
            $operation = $config['operation'] ?? 'read';
            $path = $config['path'] ?? '';
            $content = $config['content'] ?? null;

            if (empty($path)) {
                return ExecutionResult::failure('No file path provided');
            }

            $basePath = base_path();
            $fullPath = realpath($basePath . '/' . ltrim($path, '/'));
            if ($fullPath === false || !str_starts_with($fullPath, $basePath)) {
                return ExecutionResult::failure('Invalid or restricted file path');
            }

            $result = match ($operation) {
                'read' => $this->readFile($fullPath),
                'write' => $this->writeFile($fullPath, $content),
                'append' => $this->appendFile($fullPath, $content),
                'delete' => $this->deleteFile($fullPath),
                'exists' => $this->checkExists($fullPath),
                default => ExecutionResult::failure("Unknown file operation: {$operation}"),
            };

            $time = round(microtime(true) - $start, 3);
            return $result->executionTime === 0.0 
                ? ExecutionResult::success($result->output, array_merge($result->metadata, ['execution_time' => $time]), $time)
                : $result;

        } catch (\Exception $e) {
            $time = round(microtime(true) - $start, 3);
            Log::error('File Handler Error', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            return ExecutionResult::failure($e->getMessage(), 500, ['handler' => 'FileHandler'], $time);
        }
    }

    private function readFile(string $path): ExecutionResult
    {
        if (!File::exists($path)) {
            return ExecutionResult::failure("File not found: {$path}");
        }
        return ExecutionResult::success(
            File::get($path),
            ['size' => File::size($path), 'handler' => 'FileHandler']
        );
    }

    private function writeFile(string $path, ?string $content): ExecutionResult
    {
        File::put($path, $content ?? '');
        return ExecutionResult::success(
            "File written successfully: {$path}",
            ['size' => strlen($content ?? ''), 'handler' => 'FileHandler']
        );
    }

    private function appendFile(string $path, ?string $content): ExecutionResult
    {
        File::append($path, $content ?? '');
        return ExecutionResult::success(
            "File appended successfully: {$path}",
            ['handler' => 'FileHandler']
        );
    }

    private function deleteFile(string $path): ExecutionResult
    {
        if (!File::exists($path)) {
            return ExecutionResult::failure("File not found: {$path}");
        }
        File::delete($path);
        return ExecutionResult::success("File deleted: {$path}", ['handler' => 'FileHandler']);
    }

    private function checkExists(string $path): ExecutionResult
    {
        $exists = File::exists($path);
        return ExecutionResult::success(
            json_encode(['exists' => $exists, 'path' => $path]),
            ['handler' => 'FileHandler']
        );
    }
}