<?php

declare(strict_types=1);

namespace CursiveTool\Storage;

use RuntimeException;

final class FileValidator
{
    private const ALLOWED_MIMES = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private int $maxBytes;

    public function __construct(int $maxMb = 20)
    {
        $this->maxBytes = max(1, $maxMb) * 1024 * 1024;
    }

    /**
     * Validate an uploaded file entry from $_FILES.
     *
     * @param array<string, mixed> $file
     * @return array{tmp_name: string, original_name: string, mime_type: string, extension: string, size: int}
     */
    public function validate(array $file): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid file upload payload.');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('File exceeds server upload size limit.');
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('No file was uploaded.');
            default:
                throw new RuntimeException('File upload failed with error code ' . $file['error'] . '.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new RuntimeException('Uploaded file is empty.');
        }

        if ($size > $this->maxBytes) {
            $maxMb = (int) round($this->maxBytes / (1024 * 1024));
            throw new RuntimeException("File exceeds maximum allowed size of {$maxMb} MB.");
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new RuntimeException('Temporary uploaded file not found.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpPath);

        if (!isset(self::ALLOWED_MIMES[$mime])) {
            throw new RuntimeException(
                'Unsupported file type (' . $mime . '). Allowed types: PDF, PNG, JPG, WEBP.'
            );
        }

        $originalName = basename((string) ($file['name'] ?? ('upload.' . self::ALLOWED_MIMES[$mime])));
        $originalName = preg_replace('/[^\w.\- ()]/u', '_', $originalName) ?: ('upload.' . self::ALLOWED_MIMES[$mime]);

        return [
            'tmp_name' => $tmpPath,
            'original_name' => $originalName,
            'mime_type' => $mime,
            'extension' => self::ALLOWED_MIMES[$mime],
            'size' => $size,
        ];
    }
}
