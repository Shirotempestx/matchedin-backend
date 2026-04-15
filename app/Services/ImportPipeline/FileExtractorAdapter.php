<?php

namespace App\Services\ImportPipeline;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FileExtractorAdapter
{
    private const DEFAULT_EXTRACTOR_ENDPOINT = 'https://neural-extarctor.netlify.app/api/v1/extract';

    /**
     * @param array<int, UploadedFile> $files
     */
    public function extractRawText(array $files): string
    {
        if (count($files) === 0) {
            throw new RuntimeException('No files were provided for extraction.');
        }

        $endpoint = trim((string) config('services.neural_extractor.endpoint', self::DEFAULT_EXTRACTOR_ENDPOINT));
        if ($endpoint === '') {
            $endpoint = self::DEFAULT_EXTRACTOR_ENDPOINT;
        }

        $attachments = [];
        $request = Http::timeout(180);
        $verifySsl = filter_var((string) env('PREMIUM_HTTP_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN);

        if (!$verifySsl) {
            $request = $request->withoutVerifying();
        }

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $realPath = $file->getRealPath();
            if (!is_string($realPath) || $realPath === '' || !is_readable($realPath)) {
                throw new RuntimeException('Unable to read uploaded file for extraction.');
            }

            $content = file_get_contents($realPath);
            if (!is_string($content) || $content === '') {
                throw new RuntimeException('Uploaded file content is empty or unreadable.');
            }

            $attachments[] = [
                'name' => 'files',
                'contents' => $content,
                'filename' => $file->getClientOriginalName(),
            ];
        }

        foreach ($attachments as $attachment) {
            $request = $request->attach($attachment['name'], $attachment['contents'], $attachment['filename']);
        }

        try {
            $response = $request->post($endpoint);
        } catch (\Throwable $e) {
            if ($this->isSslIssuerError($e->getMessage()) && $verifySsl) {
                $retry = Http::timeout(180)->withoutVerifying();
                foreach ($attachments as $attachment) {
                    $retry = $retry->attach($attachment['name'], $attachment['contents'], $attachment['filename']);
                }
                $response = $retry->post($endpoint);
            } else {
                throw $e;
            }
        }

        if (!$response->successful()) {
            throw new RuntimeException('Data extraction service unavailable.');
        }

        return $this->extractRawTextFromPayload((array) $response->json());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractRawTextFromPayload(array $payload): string
    {
        $results = $payload['results'] ?? [];
        if (!is_array($results)) {
            throw new RuntimeException('Unexpected extractor payload format.');
        }

        $chunks = [];
        foreach ($results as $result) {
            if (!is_array($result) || (($result['success'] ?? false) !== true)) {
                continue;
            }

            $document = $result['document'] ?? ($result['data']['document'] ?? null);
            if (!is_array($document) || !isset($document['chunks']) || !is_array($document['chunks'])) {
                $documentText = $document['text'] ?? null;
                if (is_string($documentText) && trim($documentText) !== '') {
                    $chunks[] = trim($documentText);
                }
                continue;
            }

            foreach ($document['chunks'] as $chunk) {
                if (!is_array($chunk)) {
                    continue;
                }

                $text = $chunk['text'] ?? null;
                if (!is_string($text)) {
                    continue;
                }

                $trimmed = trim($text);
                if ($trimmed !== '') {
                    $chunks[] = $trimmed;
                }
            }
        }

        $rawText = trim(implode("\n", $chunks));
        if ($rawText === '') {
            throw new RuntimeException('No extractable text found in provided file.');
        }

        return $this->normalizeWhitespace($rawText);
    }

    private function isSslIssuerError(string $message): bool
    {
        $lower = strtolower($message);
        return str_contains($lower, 'curl error 60')
            || str_contains($lower, 'ssl certificate problem')
            || str_contains($lower, 'unable to get local issuer certificate');
    }

    private function normalizeWhitespace(string $raw): string
    {
        $lines = preg_split('/\R/u', $raw) ?: [];
        $normalized = [];

        foreach ($lines as $line) {
            $singleSpaced = preg_replace('/\s+/u', ' ', trim($line));
            if (is_string($singleSpaced) && $singleSpaced !== '') {
                $normalized[] = $singleSpaced;
            }
        }

        return trim(implode("\n", $normalized));
    }
}
