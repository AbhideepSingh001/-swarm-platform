<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SwarmWorkflow extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'config', 'definition', 'is_active'];

    protected $casts = [
        'config' => 'array',
        'definition' => 'array',
        'is_active' => 'boolean',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('order');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    public static function define(string $name): self
    {
        return static::firstOrCreate(['name' => $name]);
    }

    public function step(string $name, string $agent, string $task, array $dependsOn = [], array $config = []): self
    {
        $this->steps()->create([
            'name' => $name,
            'agent' => $agent,
            'task' => $task,
            'depends_on' => $dependsOn,
            'config' => $config,
            'order' => $this->steps()->count(),
        ]);

        return $this;
    }

    public function toDag(): array
    {
        return $this->steps->mapWithKeys(fn ($step) => [
            $step->name => $step->depends_on ?? []
        ])->toArray();
    }
}
