<?php

declare(strict_types=1);

namespace CursiveTool\Storage;

use CursiveTool\Bootstrap;
use RuntimeException;

final class SettingsStore
{
    private string $settingsPath;

    public function __construct(string $rootDir)
    {
        $storageDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            throw new RuntimeException("Could not create storage directory: {$storageDir}");
        }
        $this->settingsPath = $storageDir . DIRECTORY_SEPARATOR . 'settings.json';
    }

    /**
     * @return array<string, mixed>
     */
    private function readRaw(): array
    {
        if (!is_file($this->settingsPath)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($this->settingsPath), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getApiKey(): string
    {
        $saved = trim((string) ($this->readRaw()['gemini_api_key'] ?? ''));
        if ($saved !== '') {
            return $saved;
        }

        $envKey = trim((string) Bootstrap::env('GEMINI_API_KEY', ''));
        if ($envKey !== '' && $envKey !== 'your_gemini_api_key_here') {
            return $envKey;
        }

        return '';
    }

    public function getApiKeySource(): string
    {
        $saved = trim((string) ($this->readRaw()['gemini_api_key'] ?? ''));
        if ($saved !== '') {
            return 'ui';
        }

        $envKey = trim((string) Bootstrap::env('GEMINI_API_KEY', ''));
        if ($envKey !== '' && $envKey !== 'your_gemini_api_key_here') {
            return 'env';
        }

        return 'none';
    }

    public function isApiKeyConfigured(): bool
    {
        return $this->getApiKey() !== '';
    }

    public function getDefaultModel(): string
    {
        $saved = trim((string) ($this->readRaw()['default_model'] ?? ''));
        if ($saved !== '' && preg_match('/^gemini-[a-z0-9.\-]+$/i', $saved) === 1) {
            return $saved;
        }

        return (string) Bootstrap::env('GEMINI_MODEL', 'gemini-3.8-flash');
    }

    /**
     * Return safe, non-sensitive settings metadata for the UI.
     *
     * @return array{
     *     has_api_key: bool,
     *     masked_api_key: string,
     *     source: string,
     *     default_model: string
     * }
     */
    public function getPublicSettings(): array
    {
        $key = $this->getApiKey();
        return [
            'has_api_key' => $key !== '',
            'masked_api_key' => $this->maskKey($key),
            'source' => $this->getApiKeySource(),
            'default_model' => $this->getDefaultModel(),
        ];
    }

    /**
     * @param array{gemini_api_key?: string, default_model?: string, clear_api_key?: bool} $input
     * @return array{has_api_key: bool, masked_api_key: string, source: string, default_model: string}
     */
    public function saveSettings(array $input): array
    {
        $current = $this->readRaw();

        if (!empty($input['clear_api_key'])) {
            unset($current['gemini_api_key']);
        } elseif (isset($input['gemini_api_key'])) {
            $newKey = trim((string) $input['gemini_api_key']);
            if ($newKey !== '') {
                $current['gemini_api_key'] = $newKey;
            }
        }

        if (isset($input['default_model'])) {
            $model = trim((string) $input['default_model']);
            if ($model !== '' && preg_match('/^gemini-[a-z0-9.\-]+$/i', $model) === 1) {
                $current['default_model'] = $model;
            }
        }

        $current['updated_at'] = date(DATE_ATOM);

        $encoded = json_encode($current, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($encoded === false || file_put_contents($this->settingsPath, $encoded) === false) {
            throw new RuntimeException('Failed to save settings to storage/settings.json.');
        }

        return $this->getPublicSettings();
    }

    private function maskKey(string $key): string
    {
        if ($key === '') {
            return '';
        }
        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($key, 0, 4) . str_repeat('•', max(4, $len - 8)) . substr($key, -4);
    }
}
