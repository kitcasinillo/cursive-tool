<?php

declare(strict_types=1);

namespace CursiveTool;

use Dotenv\Dotenv;

final class Bootstrap
{
    private static bool $loaded = false;

    public static function rootDir(): string
    {
        return dirname(__DIR__);
    }

    public static function init(): void
    {
        if (self::$loaded) {
            return;
        }

        $root = self::rootDir();
        if (file_exists($root . '/.env')) {
            $dotenv = Dotenv::createImmutable($root);
            $dotenv->safeLoad();
        }

        self::$loaded = true;
    }

    public static function env(string $key, ?string $default = null): ?string
    {
        self::init();
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (string) $value;
    }

    public static function jsonResponse(array $payload, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function isApiKeyConfigured(): bool
    {
        $key = self::env('GEMINI_API_KEY', '');
        return $key !== '' && $key !== 'your_gemini_api_key_here';
    }
}
