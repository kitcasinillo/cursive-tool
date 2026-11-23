<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CursiveTool\Bootstrap;
use CursiveTool\Storage\DocumentStore;

Bootstrap::init();

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo 'Missing document id.';
    exit;
}

try {
    $store = new DocumentStore(Bootstrap::rootDir());
    $doc = $store->getDocument($id);
    if ($doc === null || empty($doc['stored_filename'])) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }

    $path = $store->getUploadPath((string) $doc['stored_filename']);
    if ($path === null) {
        http_response_code(404);
        echo 'File not found on disk.';
        exit;
    }

    $mime = (string) ($doc['mime_type'] ?? 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: inline; filename="' . basename((string) ($doc['original_filename'] ?? $doc['stored_filename'])) . '"');
    readfile($path);
    exit;
} catch (\Throwable $e) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}
