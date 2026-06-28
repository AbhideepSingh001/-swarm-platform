<?php
// app/Facades/ArtifactManager.php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Models\Artifact store(int $taskResultId, \Illuminate\Http\UploadedFile $file, ?string $name = null, ?array $metadata = null)
 * @method static \App\Models\Artifact storeRaw(int $taskResultId, string $content, string $name, string $type, ?array $metadata = null)
 * @method static \App\Models\Artifact|null retrieve(int $artifactId)
 * @method static string|null download(int $artifactId)
 * @method static bool delete(int $artifactId)
 * @method static array listForResult(int $taskResultId)
 * @method static string getDisk()
 */
class ArtifactManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\Artifacts\Contracts\ArtifactManagerInterface::class;
    }
}