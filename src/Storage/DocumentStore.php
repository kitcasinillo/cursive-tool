<?php

declare(strict_types=1);

namespace CursiveTool\Storage;

use RuntimeException;

final class DocumentStore
{
    private string $uploadsDir;
    private string $outputsDir;

    public function __construct(string $rootDir)
    {
        $this->uploadsDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';
        $this->outputsDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'outputs';

        foreach ([$this->uploadsDir, $this->outputsDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException("Could not create storage directory: {$dir}");
            }
        }
    }

    public function generateId(): string
    {
        return 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Move an uploaded file into storage/uploads and return its stored filename & full path.
     *
     * @return array{stored_filename: string, full_path: string}
     */
    public function saveUpload(string $tmpPath, string $id, string $extension): array
    {
        $storedFilename = $id . '.' . $extension;
        $destination = $this->uploadsDir . DIRECTORY_SEPARATOR . $storedFilename;

        $moved = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $destination)
            : rename($tmpPath, $destination);

        if (!$moved) {
            throw new RuntimeException('Failed to save uploaded file to storage.');
        }

        return [
            'stored_filename' => $storedFilename,
            'full_path' => $destination,
        ];
    }

    public function saveDocument(array $document): array
    {
        $id = $this->sanitizeId((string) ($document['id'] ?? ''));
        $document['updated_at'] = date(DATE_ATOM);

        $jsonPath = $this->jsonPath($id);
        $encoded = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($encoded === false || file_put_contents($jsonPath, $encoded) === false) {
            throw new RuntimeException('Failed to write JSON document to storage.');
        }

        // Also keep a synchronized plain-text file on disk
        $txtPath = $this->txtPath($id);
        file_put_contents($txtPath, $this->renderPlainText($document));

        return $document;
    }

    public function getDocument(string $id): ?array
    {
        $safeId = $this->sanitizeId($id);
        $path = $this->jsonPath($safeId);
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDocuments(): array
    {
        $files = glob($this->outputsDir . DIRECTORY_SEPARATOR . 'doc_*.json') ?: [];
        rsort($files);

        $items = [];
        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data) || !isset($data['id'])) {
                continue;
            }

            $pages = is_array($data['pages'] ?? null) ? $data['pages'] : [];
            $firstText = (string) ($pages[0]['edited_text'] ?? $pages[0]['raw_text'] ?? '');
            $preview = mb_substr(preg_replace('/\s+/', ' ', trim($firstText)) ?? '', 0, 110);

            $items[] = [
                'id' => $data['id'],
                'original_filename' => $data['original_filename'] ?? 'Untitled',
                'mime_type' => $data['mime_type'] ?? '',
                'uploaded_at' => $data['uploaded_at'] ?? '',
                'updated_at' => $data['updated_at'] ?? '',
                'provider' => $data['provider'] ?? '',
                'status' => $data['status'] ?? 'transcribed',
                'page_count' => count($pages),
                'preview' => $preview,
            ];
        }

        return $items;
    }

    public function deleteDocument(string $id): bool
    {
        $safeId = $this->sanitizeId($id);
        $doc = $this->getDocument($safeId);
        if ($doc === null) {
            return false;
        }

        @unlink($this->jsonPath($safeId));
        @unlink($this->txtPath($safeId));

        if (!empty($doc['stored_filename'])) {
            $uploadFile = $this->uploadsDir . DIRECTORY_SEPARATOR . basename((string) $doc['stored_filename']);
            if (is_file($uploadFile)) {
                @unlink($uploadFile);
            }
        }

        return true;
    }

    public function getUploadPath(string $storedFilename): ?string
    {
        $safe = basename($storedFilename);
        if ($safe === '' || !preg_match('/^doc_[0-9_a-f]+\.(pdf|png|jpg|webp)$/i', $safe)) {
            return null;
        }
        $path = $this->uploadsDir . DIRECTORY_SEPARATOR . $safe;
        return is_file($path) ? $path : null;
    }

    public function renderPlainText(array $document): string
    {
        $lines = [];
        $lines[] = 'Document: ' . ($document['original_filename'] ?? $document['id'] ?? 'Untitled');
        $lines[] = 'Processed: ' . ($document['uploaded_at'] ?? '');
        $lines[] = 'Provider: ' . ($document['provider'] ?? '');

        if (!empty($document['extracted_fields']) && is_array($document['extracted_fields'])) {
            $lines[] = '';
            $lines[] = '--- Extracted Fields ---';
            foreach ($document['extracted_fields'] as $key => $val) {
                $lines[] = "{$key}: {$val}";
            }
        }

        $pages = is_array($document['pages'] ?? null) ? $document['pages'] : [];
        foreach ($pages as $page) {
            $pageNum = (int) ($page['page_number'] ?? 1);
            $text = (string) ($page['edited_text'] ?? $page['raw_text'] ?? '');
            $lines[] = '';
            $lines[] = "=== Page {$pageNum} ===";
            $lines[] = trim($text);
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function sanitizeId(string $id): string
    {
        if (!preg_match('/^doc_[0-9_a-f]+$/i', $id)) {
            throw new RuntimeException('Invalid document identifier.');
        }
        return $id;
    }

    private function jsonPath(string $safeId): string
    {
        return $this->outputsDir . DIRECTORY_SEPARATOR . $safeId . '.json';
    }

    private function txtPath(string $safeId): string
    {
        return $this->outputsDir . DIRECTORY_SEPARATOR . $safeId . '.txt';
    }
}
