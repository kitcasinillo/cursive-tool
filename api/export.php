<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CursiveTool\Bootstrap;
use CursiveTool\Storage\DocumentStore;

Bootstrap::init();

$id = trim((string) ($_GET['id'] ?? ''));
$format = strtolower(trim((string) ($_GET['format'] ?? 'json')));

if ($id === '' || !in_array($format, ['json', 'txt'], true)) {
    Bootstrap::jsonResponse(['ok' => false, 'error' => 'Provide valid id and format (json or txt).'], 400);
}

try {
    $store = new DocumentStore(Bootstrap::rootDir());
    $doc = $store->getDocument($id);
    if ($doc === null) {
        Bootstrap::jsonResponse(['ok' => false, 'error' => 'Document not found.'], 404);
    }

    $baseName = pathinfo((string) ($doc['original_filename'] ?? $id), PATHINFO_FILENAME);
    $safeBase = preg_replace('/[^\w.\-]/', '_', $baseName) ?: $id;

    if ($format === 'txt') {
        $content = $store->renderPlainText($doc);
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeBase . '.txt"');
        echo $content;
        exit;
    }

    $content = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $safeBase . '.json"');
    echo $content;
    exit;
} catch (\Throwable $e) {
    Bootstrap::jsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
}
