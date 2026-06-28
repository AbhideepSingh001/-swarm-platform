<?php

namespace App\Services\Results;

use App\Models\Artifact;
use App\Models\TaskResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArtifactManager
{
    public function __construct(
        private string $disk = 'local'
    ) {}

    public function storeFile(TaskResult $result, UploadedFile $file, string $name = null, array $metadata = []): Artifact
    {
        $path = $file->store('artifacts/' . $result->id, $this->disk);

        return Artifact::create([
            'task_result_id' => $result->id,
            'name' => $name ?? $file->getClientOriginalName(),
            'type' => 'file',
            'mime_type' => $file->getMimeType(),
            'disk' => $this->disk,
            'path' => $path,
            'size_bytes' => $file->getSize(),
            'metadata' => $metadata,
        ]);
    }

    public function storeJson(TaskResult $result, array $data, string $name, array $metadata = []): Artifact
    {
        $filename = Str::slug($name) . '-' . time() . '.json';
        $path = 'artifacts/' . $result->id . '/' . $filename;

        Storage::disk($this->disk)->put($path, json_encode($data, JSON_PRETTY_PRINT));

        return Artifact::create([
            'task_result_id' => $result->id,
            'name' => $name,
            'type' => 'json',
            'mime_type' => 'application/json',
            'disk' => $this->disk,
            'path' => $path,
            'size_bytes' => Storage::disk($this->disk)->size($path),
            'metadata' => $metadata,
        ]);
    }

    public function storeText(TaskResult $result, string $content, string $name, array $metadata = []): Artifact
    {
        $filename = Str::slug($name) . '-' . time() . '.txt';
        $path = 'artifacts/' . $result->id . '/' . $filename;

        Storage::disk($this->disk)->put($path, $content);

        return Artifact::create([
            'task_result_id' => $result->id,
            'name' => $name,
            'type' => 'text',
            'mime_type' => 'text/plain',
            'disk' => $this->disk,
            'path' => $path,
            'size_bytes' => Storage::disk($this->disk)->size($path),
            'metadata' => $metadata,
        ]);
    }

    public function delete(Artifact $artifact): bool
    {
        return $artifact->delete();
    }

    public function setDisk(string $disk): self
    {
        $this->disk = $disk;
        return $this;
    }
}