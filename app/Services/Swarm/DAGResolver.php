<?php

namespace App\Services\Swarm;

use Illuminate\Support\Collection;

class DAGResolver
{
    /**
     * @param array<string, string[]> $graph Step name => array of dependency step names
     * @return string[] Topologically sorted step names
     * @throws \RuntimeException If cycle detected
     */
    public function resolve(array $graph): array
    {
        $inDegree = [];
        $adjacency = [];

        foreach ($graph as $node => $dependencies) {
            $inDegree[$node] = $inDegree[$node] ?? 0;
            $adjacency[$node] = $adjacency[$node] ?? [];

            foreach ($dependencies as $dep) {
                $adjacency[$dep][] = $node;
                $inDegree[$node] = ($inDegree[$node] ?? 0) + 1;
                $inDegree[$dep] = $inDegree[$dep] ?? 0;
            }
        }

        $queue = collect($inDegree)
            ->filter(fn ($degree) => $degree === 0)
            ->keys()
            ->all();

        $sorted = [];

        while (!empty($queue)) {
            $node = array_shift($queue);
            $sorted[] = $node;

            foreach ($adjacency[$node] ?? [] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($sorted) !== count($inDegree)) {
            throw new \RuntimeException('Cycle detected in workflow DAG');
        }

        return $sorted;
    }

    /**
     * @param array<string, string[]> $graph
     * @return Collection<Collection<string>> Groups of steps that can run in parallel
     */
    public function getExecutionLevels(array $graph): Collection
    {
        $sorted = $this->resolve($graph);
        $levels = collect();
        $completed = [];

        while (!empty($sorted)) {
            $level = collect($sorted)->filter(function ($node) use ($graph, $completed) {
                return collect($graph[$node] ?? [])
                    ->every(fn ($dep) => in_array($dep, $completed));
            });

            if ($level->isEmpty()) {
                throw new \RuntimeException('Unable to determine execution levels');
            }

            $levels->push($level->values());
            $completed = array_merge($completed, $level->all());
            $sorted = array_diff($sorted, $level->all());
        }

        return $levels;
    }

    /**
     * @param array<string, string[]> $graph
     */
    public function hasCycle(array $graph): bool
    {
        try {
            $this->resolve($graph);
            return false;
        } catch (\RuntimeException $e) {
            return true;
        }
    }

    /**
     * @param array<string, string[]> $graph
     * @return string[] Nodes in the cycle, if any
     */
    public function findCycle(array $graph): array
    {
        $visited = [];
        $recStack = [];
        $cycle = [];

        $dfs = function ($node) use (&$visited, &$recStack, &$cycle, $graph, &$dfs) {
            $visited[$node] = true;
            $recStack[$node] = true;

            foreach ($graph[$node] ?? [] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    if ($dfs($neighbor)) {
                        if (!empty($cycle) && $cycle[0] !== $neighbor) {
                            $cycle[] = $neighbor;
                        }
                        return true;
                    }
                } elseif ($recStack[$neighbor]) {
                    $cycle = [$neighbor];
                    return true;
                }
            }

            $recStack[$node] = false;
            return false;
        };

        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                if ($dfs($node) && !empty($cycle)) {
                    break;
                }
            }
        }

        return array_reverse($cycle);
    }
}