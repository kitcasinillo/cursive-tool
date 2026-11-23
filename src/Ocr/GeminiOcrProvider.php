<?php

declare(strict_types=1);

namespace CursiveTool\Ocr;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class GeminiOcrProvider implements OcrProviderInterface
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    private const FALLBACK_MODELS = [
        'gemini-3.8-flash',
        'gemini-3.7-flash',
        'gemini-3.6-flash',
        'gemini-3.5-flash',
        'gemini-3.1-flash-lite',
    ];

    private Client $http;
    private string $apiKey;
    private string $defaultModel;

    public function __construct(string $apiKey, string $defaultModel = 'gemini-3.8-flash', ?Client $http = null)
    {
        $this->apiKey = trim($apiKey);
        $this->defaultModel = trim($defaultModel) !== '' ? trim($defaultModel) : 'gemini-3.8-flash';
        $this->http = $http ?? new Client([
            'timeout' => 120,
            'connect_timeout' => 15,
        ]);
    }

    public function transcribe(string $filePath, string $mimeType, array $options = []): array
    {
        if ($this->apiKey === '' || $this->apiKey === 'your_gemini_api_key_here') {
            throw new RuntimeException(
                'Gemini API key is not configured. Click "API Settings" in the top bar to enter your key.'
            );
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException('Uploaded file could not be read for transcription.');
        }

        $model = $this->sanitizeModel($options['model'] ?? $this->defaultModel);
        $customFields = array_values(array_filter(
            array_map('trim', $options['custom_fields'] ?? []),
            static fn(string $f): bool => $f !== ''
        ));

        $rawBytes = file_get_contents($filePath);
        if ($rawBytes === false) {
            throw new RuntimeException('Failed to read file contents.');
        }

        $prompt = $this->buildPrompt($customFields);
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => base64_encode($rawBytes),
                            ],
                        ],
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->responseSchema(),
            ],
        ];

        $candidates = $this->buildCandidateModels($model);
        $response = null;
        $usedModel = $model;
        $lastError = 'Unknown Gemini API error.';

        foreach ($candidates as $index => $candidateModel) {
            $maxAttempts = $index === 0 ? 2 : 1;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $endpoint = self::API_BASE . rawurlencode($candidateModel) . ':generateContent?key=' . rawurlencode($this->apiKey);
                try {
                    $response = $this->http->post($endpoint, [
                        'headers' => ['Content-Type' => 'application/json'],
                        'json' => $payload,
                    ]);
                    $usedModel = $candidateModel;
                    break 2;
                } catch (GuzzleException $e) {
                    $statusCode = 0;
                    $message = $e->getMessage();

                    if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
                        $statusCode = $e->getResponse()->getStatusCode();
                        $errBody = (string) $e->getResponse()->getBody();
                        $decodedErr = json_decode($errBody, true);
                        if (isset($decodedErr['error']['message'])) {
                            $message = $decodedErr['error']['message'];
                        }
                    }

                    $lastError = "{$candidateModel}: {$message}";

                    // Non-retryable auth/request errors should fail immediately
                    if (in_array($statusCode, [400, 401, 403], true)) {
                        throw new RuntimeException('Gemini API request failed: ' . $message, 0, $e);
                    }

                    // Model unavailable (404) -> jump straight to next fallback model
                    if ($statusCode === 404) {
                        break;
                    }

                    // Transient overload / rate limit (429, 500, 502, 503, 504) -> brief backoff then retry/fallback
                    if ($attempt < $maxAttempts) {
                        usleep(1200000);
                    }
                }
            }
        }

        if ($response === null) {
            throw new RuntimeException('Gemini API request failed across all fallback models. Last error: ' . $lastError);
        }

        $body = json_decode((string) $response->getBody(), true);
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('Gemini API returned an empty transcription response.');
        }

        $parsed = json_decode($text, true);
        if (!is_array($parsed) || !isset($parsed['pages']) || !is_array($parsed['pages'])) {
            throw new RuntimeException('Gemini API returned invalid JSON structure.');
        }

        $pages = [];
        foreach ($parsed['pages'] as $idx => $page) {
            $rawText = (string) ($page['raw_text'] ?? '');
            $uncertain = [];
            if (isset($page['uncertain_words']) && is_array($page['uncertain_words'])) {
                foreach ($page['uncertain_words'] as $word) {
                    if (is_string($word) && trim($word) !== '') {
                        $uncertain[] = trim($word);
                    }
                }
            }

            $pages[] = [
                'page_number' => (int) ($page['page_number'] ?? ($idx + 1)),
                'raw_text' => $rawText,
                'edited_text' => $rawText,
                'uncertain_words' => $uncertain,
                'notes' => (string) ($page['notes'] ?? ''),
            ];
        }

        $extractedFields = [];
        if (isset($parsed['extracted_fields']) && is_array($parsed['extracted_fields'])) {
            foreach ($parsed['extracted_fields'] as $entry) {
                $k = trim((string) ($entry['key'] ?? ''));
                $v = trim((string) ($entry['value'] ?? ''));
                if ($k !== '') {
                    $extractedFields[$k] = $v;
                }
            }
        }

        return [
            'provider' => $usedModel,
            'pages' => $pages,
            'extracted_fields' => $extractedFields,
        ];
    }

    /**
     * @return string[]
     */
    private function buildCandidateModels(string $primaryModel): array
    {
        $models = [$primaryModel];
        foreach (self::FALLBACK_MODELS as $fallback) {
            if (!in_array($fallback, $models, true)) {
                $models[] = $fallback;
            }
        }
        return $models;
    }

    private function sanitizeModel(string $model): string
    {
        $clean = trim($model);
        if (preg_match('/^gemini-[a-z0-9.\-]+$/i', $clean) === 1) {
            return $clean;
        }
        return $this->defaultModel;
    }

    /**
     * @param string[] $customFields
     */
    private function buildPrompt(array $customFields): string
    {
        $base = <<<PROMPT
You are an expert paleographer and handwritten text recognition (HTR) specialist focusing on cursive handwriting.
Carefully transcribe ALL handwritten cursive (and any printed) text from every page of this document.

Rules:
1. Preserve the original spelling, punctuation, line breaks, and paragraph structure as closely as possible.
2. Do NOT invent or hallucinate words that are not visible.
3. If a word is difficult to read, write your best guess in brackets like [uncertain: word] and also add it to uncertain_words.
4. If a word is completely unreadable, write [illegible] and note it in uncertain_words.
5. For multi-page PDFs, output one entry in the pages array per page in order (starting at page_number 1).
6. Keep notes concise (mention ink quality, crossed-out words, margins, or slant issues if relevant).
PROMPT;

        if ($customFields !== []) {
            $fieldList = implode(', ', $customFields);
            $base .= "\n\nAdditionally, extract the following structured fields into extracted_fields (use empty string if not found): {$fieldList}.";
        } else {
            $base .= "\n\nIf obvious metadata fields appear (such as Date, Author/Sender, Recipient, Title, or Subject), include them in extracted_fields; otherwise return an empty array.";
        }

        return $base;
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'pages' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'page_number' => ['type' => 'INTEGER'],
                            'raw_text' => ['type' => 'STRING'],
                            'uncertain_words' => [
                                'type' => 'ARRAY',
                                'items' => ['type' => 'STRING'],
                            ],
                            'notes' => ['type' => 'STRING'],
                        ],
                        'required' => ['page_number', 'raw_text', 'uncertain_words', 'notes'],
                    ],
                ],
                'extracted_fields' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'key' => ['type' => 'STRING'],
                            'value' => ['type' => 'STRING'],
                        ],
                        'required' => ['key', 'value'],
                    ],
                ],
            ],
            'required' => ['pages', 'extracted_fields'],
        ];
    }
}
