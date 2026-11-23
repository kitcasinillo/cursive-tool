<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CursiveTool\Bootstrap;
use CursiveTool\Storage\DocumentStore;
use CursiveTool\Storage\SettingsStore;

Bootstrap::init();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$store = new DocumentStore(Bootstrap::rootDir());

try {
    if ($method === 'GET') {
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id !== '') {
            $doc = $store->getDocument($id);
            if ($doc === null) {
                Bootstrap::jsonResponse(['ok' => false, 'error' => 'Document not found.'], 404);
            }
            Bootstrap::jsonResponse(['ok' => true, 'document' => $doc]);
        }

        $settings = new SettingsStore(Bootstrap::rootDir());
        Bootstrap::jsonResponse([
            'ok' => true,
            'api_key_configured' => $settings->isApiKeyConfigured(),
            'default_model' => $settings->getDefaultModel(),
            'documents' => $store->listDocuments(),
        ]);
    }

    if ($method === 'PUT' || $method === 'POST') {
        $raw = (string) file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body) || empty($body['id'])) {
            Bootstrap::jsonResponse(['ok' => false, 'error' => 'Invalid JSON payload or missing document id.'], 400);
        }

        $doc = $store->getDocument((string) $body['id']);
        if ($doc === null) {
            Bootstrap::jsonResponse(['ok' => false, 'error' => 'Document not found.'], 404);
        }

        if (isset($body['pages']) && is_array($body['pages'])) {
            $updatedPages = [];
            foreach ($doc['pages'] as $idx => $existingPage) {
                $incoming = $body['pages'][$idx] ?? null;
                if (is_array($incoming) && isset($incoming['edited_text'])) {
                    $existingPage['edited_text'] = (string) $incoming['edited_text'];
                }
                if (is_array($incoming) && isset($incoming['notes'])) {
                    $existingPage['notes'] = (string) $incoming['notes'];
                }
                $updatedPages[] = $existingPage;
            }
            $doc['pages'] = $updatedPages;
        }

        if (isset($body['extracted_fields']) && is_array($body['extracted_fields'])) {
            $cleanFields = [];
            foreach ($body['extracted_fields'] as $k => $v) {
                $key = trim((string) $k);
                if ($key !== '') {
                    $cleanFields[$key] = trim((string) $v);
                }
            }
            $doc['extracted_fields'] = $cleanFields;
        }

        $doc['status'] = isset($body['status']) && in_array($body['status'], ['transcribed', 'reviewed'], true)
            ? $body['status']
            : 'reviewed';

        $saved = $store->saveDocument($doc);
        Bootstrap::jsonResponse(['ok' => true, 'document' => $saved]);
    }

    if ($method === 'DELETE') {
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') {
            Bootstrap::jsonResponse(['ok' => false, 'error' => 'Missing document id.'], 400);
        }

        $deleted = $store->deleteDocument($id);
        if (!$deleted) {
            Bootstrap::jsonResponse(['ok' => false, 'error' => 'Document not found.'], 404);
        }

        Bootstrap::jsonResponse(['ok' => true, 'deleted' => $id]);
    }

    Bootstrap::jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
} catch (\Throwable $e) {
    Bootstrap::jsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
}
