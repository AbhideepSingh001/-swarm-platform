<?php

namespace App\Services\Swarm;

use App\Models\DeadLetter;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeadLetterQueue
{
    public function record(array $payload): DeadLetter
    {
        $exception = $payload['error'] ?? null;
        $failureCategory = app(FailureAnalyzer::class)->categorize($exception);

        $deadLetter = DeadLetter::create([
            'execution_id' => $payload['execution_id'],
            'step_id' => $payload['step_id'],
            'agent_id' => $payload['agent_id'],
            'failure_category' => $failureCategory,
            'error_message' => $exception instanceof Throwable ? $exception->getMessage() : (string) $exception,
            'error_trace' => $exception instanceof Throwable ? $this->serializeTrace($exception) : [],
            'step_config' => $payload['step_config'] ?? [],
            'context' => $payload['context'] ?? [],
            'retry_count' => $payload['retry_count'] ?? 0,
            'failed_at' => now(),
            'status' => 'open',
        ]);

        Log::warning('Dead letter recorded', [
            'dead_letter_id' => $deadLetter->id,
            'execution_id' => $deadLetter->execution_id,
            'step_id' => $deadLetter->step_id,
            'category' => $failureCategory,
        ]);

        return $deadLetter;
    }

    public function retrieve(int $id): ?DeadLetter
    {
        return DeadLetter::find($id);
    }

    public function listOpen(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DeadLetter::open()->withFilters($filters);

        return $query->latest('failed_at')->paginate($filters['per_page'] ?? 25);
    }

    public function pruneResolved(int $days = 30): int
    {
        return DeadLetter::whereIn('status', ['resolved', 'dismissed'])
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();
    }

    protected function serializeTrace(Throwable $exception): array
    {
        return [
            'class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => collect($exception->getTrace())
                ->take(20)
                ->map(fn ($frame) => [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                    'class' => $frame['class'] ?? null,
                ])
                ->toArray(),
        ];
    }
}
