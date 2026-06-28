<?php
// app/Services/Artifacts/ArtifactManager.php

namespace App\Services\Artifacts;

use App\Models\Artifact;
use App\Services\Artifacts\Contracts\ArtifactManagerInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArtifactManager implements ArtifactManagerInterface
{
    private string $disk;
    private string $basePath;

    public function __construct(?string $disk = null, ?string $basePath = null)
    {
        $this->disk = $disk ?? config('swarm.artifacts.disk', 'local');
        $this->basePath = $basePath ?? config('swarm.artifacts.path', 'artifacts');
    }

    public function store(int $taskResultId, UploadedFile $file, ?string $name = null, ?array $metadata = null): Artifact
    {
        $fileName = $name ?? $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $type = $this->inferTypeFromExtension($extension);

        $path = $this->generatePath($taskResultId, $extension);
        $storedPath = $file->storeAs($this->basePath, $path, $this->disk);

        return Artifact::create([
            'task_result_id' => $taskResultId,
            'name' => $fileName,
            'type' => $type,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'disk' => $this->disk,
            'path' => $storedPath,
            'size_bytes' => $file->getSize(),
            'metadata' => $metadata,
        ]);
    }

    public function storeRaw(int $taskResultId, string $content, string $name, string $type, ?array $metadata = null): Artifact
    {
        $extension = $this->inferExtensionFromType($type);
        $path = $this->generatePath($taskResultId, $extension);
        $fullPath = "{$this->basePath}/{$path}";

        Storage::disk($this->disk)->put($fullPath, $content);

        return Artifact::create([
            'task_result_id' => $taskResultId,
            'name' => $name,
            'type' => $type,
            'mime_type' => $this->inferMimeType($type),
            'disk' => $this->disk,
            'path' => $fullPath,
            'size_bytes' => strlen($content),
            'metadata' => $metadata,
        ]);
    }

    public function retrieve(int $artifactId): ?Artifact
    {
        return Artifact::find($artifactId);
    }

    public function download(int $artifactId): ?string
    {
        $artifact = $this->retrieve($artifactId);

        if (!$artifact) {
            return null;
        }

        return $artifact->getContent();
    }

    public function delete(int $artifactId): bool
    {
        $artifact = $this->retrieve($artifactId);

        if (!$artifact) {
            return false;
        }

        return $artifact->delete(); // Model boot handles file cleanup
    }

    public function listForResult(int $taskResultId): array
    {
        return Artifact::where('task_result_id', $taskResultId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    private function generatePath(int $taskResultId, string $extension): string
    {
        $date = now()->format('Y/m/d');
        $uuid = Str::uuid()->toString();

        return "{$date}/{$taskResultId}/{$uuid}.{$extension}";
    }

    private function inferTypeFromExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'json' => 'json',
            'csv' => 'csv',
            'txt' => 'text',
            'png', 'jpg', 'jpeg', 'gif', 'webp' => 'image',
            default => 'binary',
        };
    }

    private function inferExtensionFromType(string $type): string
    {
        return match ($type) {
            'json' => 'json',
            'csv' => 'csv',
            'text' => 'txt',
            'image' => 'png',
            default => 'bin',
        };
    }

    private function inferMimeType(string $type): string
    {
        return match ($type) {
            'json' => 'application/json',
            'csv' => 'text/csv',
            'text' => 'text/plain',
            'image' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}