<?php
// app/Services/Artifacts/Contracts/ArtifactManagerInterface.php

namespace App\Services\Artifacts\Contracts;

use App\Models\Artifact;
use Illuminate\Http\UploadedFile;

interface ArtifactManagerInterface
{
    public function store(int $taskResultId, UploadedFile $file, ?string $name = null, ?array $metadata = null): Artifact;

    public function storeRaw(int $taskResultId, string $content, string $name, string $type, ?array $metadata = null): Artifact;

    public function retrieve(int $artifactId): ?Artifact;

    public function download(int $artifactId): ?string;

    public function delete(int $artifactId): bool;

    public function listForResult(int $taskResultId): array;

    public function getDisk(): string;
}