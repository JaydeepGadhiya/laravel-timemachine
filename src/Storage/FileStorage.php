<?php

namespace Jaydeep\LaravelTimeMachine\Storage;

/**
 * Simple, dependency-free storage driver: one JSON file per request profile.
 * File names are prefixed with the request's millisecond timestamp so a plain
 * reverse filename sort yields newest-first ordering without opening a file.
 */
class FileStorage implements StorageContract
{
    /** @var string */
    protected $path;

    /** @var int */
    protected $maxRecords;

    public function __construct($path, $maxRecords = 100)
    {
        $this->path       = rtrim($path, '/\\');
        $this->maxRecords = max(1, (int) $maxRecords);
    }

    public function store(array $profile)
    {
        $this->ensureDirectory();

        $startedAt = isset($profile['started_at']) ? (float) $profile['started_at'] : microtime(true);
        $id        = sprintf('%015d_%s', (int) round($startedAt * 1000), substr(md5(uniqid('tm', true)), 0, 8));

        $profile = array_merge(['id' => $id], $profile);

        file_put_contents(
            $this->filePath($id),
            json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)
        );

        $this->prune();

        return $id;
    }

    public function all()
    {
        $summaries = [];

        foreach ($this->files() as $file) {
            $profile = $this->decode($file);

            if ($profile === null) {
                continue;
            }

            $summaries[] = [
                'id'          => $profile['id'] ?? basename($file, '.json'),
                'method'      => $profile['method'] ?? 'GET',
                'uri'         => $profile['uri'] ?? '/',
                'status'      => $profile['status'] ?? null,
                'route'       => $profile['route'] ?? null,
                'total_ms'    => $profile['total_ms'] ?? 0,
                'query_count' => $profile['query_count'] ?? 0,
                'memory_peak' => $profile['memory_peak'] ?? 0,
                'started_at'  => $profile['started_at'] ?? 0,
            ];
        }

        return $summaries;
    }

    public function find($id)
    {
        $id   = $this->sanitize($id);
        $file = $this->filePath($id);

        return is_file($file) ? $this->decode($file) : null;
    }

    public function clear()
    {
        foreach ($this->files() as $file) {
            @unlink($file);
        }
    }

    /**
     * Delete the oldest profiles once the cap is exceeded.
     */
    protected function prune()
    {
        $files = $this->files();
        $count = count($files);

        if ($count <= $this->maxRecords) {
            return;
        }

        // files() is newest-first; the tail holds the oldest.
        foreach (array_slice($files, $this->maxRecords) as $file) {
            @unlink($file);
        }
    }

    /**
     * @return array<int,string> Absolute file paths, newest first.
     */
    protected function files()
    {
        if (! is_dir($this->path)) {
            return [];
        }

        $files = glob($this->path.DIRECTORY_SEPARATOR.'*.json');

        if ($files === false) {
            return [];
        }

        rsort($files); // filename is timestamp-prefixed → reverse = newest first

        return $files;
    }

    protected function decode($file)
    {
        $raw = @file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    protected function filePath($id)
    {
        return $this->path.DIRECTORY_SEPARATOR.$this->sanitize($id).'.json';
    }

    protected function sanitize($id)
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $id);
    }

    protected function ensureDirectory()
    {
        if (! is_dir($this->path)) {
            @mkdir($this->path, 0755, true);
        }
    }
}
