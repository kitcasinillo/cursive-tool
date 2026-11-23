<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CursiveTool\Bootstrap;
use CursiveTool\Storage\SettingsStore;

Bootstrap::init();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$settings = new SettingsStore(Bootstrap::rootDir());

try {
    if ($method === 'GET') {
        Bootstrap::jsonResponse([
            'ok' => true,
            'settings' => $settings->getPublicSettings(),
        ]);
    }

    if ($method === 'POST' || $method === 'PUT') {
        $raw = (string) file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            Bootstrap::jsonResponse(['ok' => false, 'error' => 'Invalid JSON payload.'], 400);
        }

        $saved = $settings->saveSettings($body);
        Bootstrap::jsonResponse([
            'ok' => true,
            'settings' => $saved,
        ]);
    }

    Bootstrap::jsonResponse(['ok' => false, 'error' => 'Method not allowed.'], 405);
} catch (\Throwable $e) {
    Bootstrap::jsonResponse([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 400);
}
