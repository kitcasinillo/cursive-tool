<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CursiveTool\Bootstrap;
use CursiveTool\Ocr\GeminiOcrProvider;
use CursiveTool\Storage\DocumentStore;
use CursiveTool\Storage\FileValidator;
use CursiveTool\Storage\SettingsStore;

Bootstrap::init();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Bootstrap::jsonResponse(['error' => 'Method not allowed. Use POST.'], 405);
}

try {
    if (!isset($_FILES['file'])) {
        Bootstrap::jsonResponse(['error' => 'No file field received.'], 400);
    }

    $maxMb = (int) Bootstrap::env('MAX_UPLOAD_MB', '20');
    $validator = new FileValidator($maxMb);
    $validated = $validator->validate($_FILES['file']);

    $store = new DocumentStore(Bootstrap::rootDir());
    $id = $store->generateId();
    $savedUpload = $store->saveUpload($validated['tmp_name'], $id, $validated['extension']);

    $customFieldsRaw = (string) ($_POST['custom_fields'] ?? '');
    $customFields = array_values(array_filter(
        array_map('trim', explode(',', $customFieldsRaw)),
        static fn(string $v): bool => $v !== ''
    ));

    $settings = new SettingsStore(Bootstrap::rootDir());
    $requestedModel = trim((string) ($_POST['model'] ?? ''));
    $defaultModel = $settings->getDefaultModel();
    $apiKey = $settings->getApiKey();

    $provider = new GeminiOcrProvider($apiKey, $defaultModel);

    try {
        $result = $provider->transcribe($savedUpload['full_path'], $validated['mime_type'], [
            'model' => $requestedModel !== '' ? $requestedModel : $defaultModel,
            'custom_fields' => $customFields,
        ]);
    } catch (\Throwable $ocrError) {
        // Clean up orphaned upload if transcription failed
        if (is_file($savedUpload['full_path'])) {
            @unlink($savedUpload['full_path']);
        }
        throw $ocrError;
    }

    $document = [
        'id' => $id,
        'original_filename' => $validated['original_name'],
        'stored_filename' => $savedUpload['stored_filename'],
        'mime_type' => $validated['mime_type'],
        'file_size_bytes' => $validated['size'],
        'uploaded_at' => date(DATE_ATOM),
        'updated_at' => date(DATE_ATOM),
        'provider' => $result['provider'],
        'status' => 'transcribed',
        'custom_fields_requested' => $customFields,
        'pages' => $result['pages'],
        'extracted_fields' => $result['extracted_fields'],
    ];

    $saved = $store->saveDocument($document);

    Bootstrap::jsonResponse([
        'ok' => true,
        'document' => $saved,
    ], 201);
} catch (\Throwable $e) {
    Bootstrap::jsonResponse([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 400);
}
