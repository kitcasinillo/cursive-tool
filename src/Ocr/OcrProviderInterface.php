<?php

declare(strict_types=1);

namespace CursiveTool\Ocr;

interface OcrProviderInterface
{
    /**
     * Transcribe a cursive handwriting image or PDF into structured page data.
     *
     * @param string $filePath Absolute path to the uploaded file.
     * @param string $mimeType Validated MIME type (application/pdf, image/png, image/jpeg, image/webp).
     * @param array{model?: string, custom_fields?: string[]} $options
     * @return array{
     *     provider: string,
     *     pages: array<int, array{
     *         page_number: int,
     *         raw_text: string,
     *         edited_text: string,
     *         uncertain_words: string[],
     *         notes: string
     *     }>,
     *     extracted_fields: array<string, string>
     * }
     */
    public function transcribe(string $filePath, string $mimeType, array $options = []): array;
}
