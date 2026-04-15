<?php

namespace App\Services\ImportPipeline;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class UrlExtractorAdapter
{
    public function extractRawText(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new RuntimeException('A valid URL is required for extraction.');
        }

        [$content, $contentType] = $this->fetchContent($url);

        if ($content === '') {
            throw new RuntimeException('Website could not be fetched.');
        }

        $html = $this->toHtml($url, $content, $contentType);
        $cleaned = $this->stripNoiseAndExtractText($html);

        if ($cleaned === '') {
            throw new RuntimeException('No extractable text found at the provided URL.');
        }

        if (Str::contains($cleaned, ['Target URL returned error 999', 'HTTP ERROR 999'])) {
            throw new RuntimeException('Website blocked access (Anti-scraping protection). Try importing a PDF instead.');
        }

        return $cleaned;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function fetchContent(string $url): array
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9,fr;q=0.8',
            'Cache-Control' => 'no-cache',
        ];

        $verifySsl = filter_var((string) env('PREMIUM_HTTP_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN);

        try {
            $request = Http::timeout(45)->withHeaders($headers);
            if (!$verifySsl) {
                $request = $request->withoutVerifying();
            }

            $direct = $request->get($url);
            $body = trim((string) $direct->body());

            if ($direct->successful() && $body !== '') {
                return [$body, (string) $direct->header('content-type')];
            }
        } catch (\Throwable $e) {
            // First retry bypassing SSL if it failed with SSL issuer error
            if ($verifySsl && $this->isSslIssuerError($e->getMessage())) {
                try {
                    $retry = Http::timeout(45)->withoutVerifying()->withHeaders($headers);
                    $directRetry = $retry->get($url);
                    $retryBody = trim((string) $directRetry->body());
                    if ($directRetry->successful() && $retryBody !== '') {
                        return [$retryBody, (string) $directRetry->header('content-type')];
                    }
                } catch (\Throwable $eRetry) {
                    // Fallthrough to proxy
                }
            }
        }

        try {
            $proxyUrl = 'https://r.jina.ai/http://' . preg_replace('#^https?://#', '', $url);
            $proxy = Http::timeout(90)
                ->withHeaders([
                    'User-Agent' => $headers['User-Agent'],
                    'Accept' => 'text/plain, text/html, application/json;q=0.9, */*;q=0.8',
                ]);

            if (!$verifySsl) {
                $proxy = $proxy->withoutVerifying();
            }

            $proxyResponse = $proxy->get($proxyUrl);
            $proxyBody = trim((string) $proxyResponse->body());

            if ($proxyResponse->successful() && $proxyBody !== '') {
                return [$proxyBody, (string) $proxyResponse->header('content-type')];
            }
        } catch (\Throwable $eProxy) {
            if ($verifySsl && $this->isSslIssuerError($eProxy->getMessage())) {
                try {
                    $proxyRetry = Http::timeout(90)->withoutVerifying()
                        ->withHeaders([
                            'User-Agent' => $headers['User-Agent'],
                            'Accept' => 'text/plain, text/html, application/json;q=0.9, */*;q=0.8',
                        ]);
                    $proxyResponse = $proxyRetry->get($proxyUrl);
                    $proxyBody = trim((string) $proxyResponse->body());
                    if ($proxyResponse->successful() && $proxyBody !== '') {
                        return [$proxyBody, (string) $proxyResponse->header('content-type')];
                    }
                } catch (\Throwable $eProxyRetry) {
                }
            }
            throw new RuntimeException('Website could not be fetched due to network connection error: ' . $eProxy->getMessage());
        }

        throw new RuntimeException('Website could not be fetched. Some sites block automated access or require rendering JavaScript.');
    }

    private function isSslIssuerError(string $message): bool
    {
        $lower = strtolower($message);
        return str_contains($lower, 'curl error 60')
            || str_contains($lower, 'ssl certificate problem')
            || str_contains($lower, 'unable to get local issuer certificate');
    }

    private function toHtml(string $url, string $content, string $contentType): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return '';
        }

        if (Str::contains(strtolower($contentType), 'html') || Str::contains(strtolower($trimmed), '<html')) {
            return $trimmed;
        }

        return '<html><head><meta charset="utf-8"><title>'
            . e($url)
            . '</title></head><body><pre style="white-space: pre-wrap; font-family: Arial, sans-serif;">'
            . e($trimmed)
            . '</pre></body></html>';
    }

    private function stripNoiseAndExtractText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        try {
            $previous = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $loaded = $dom->loadHTML($html);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (!$loaded) {
                return $this->normalizeWhitespace(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            $xpath = new DOMXPath($dom);
            $noise = $xpath->query('//script|//style|//nav|//footer|//header|//noscript|//svg|//form|//aside|//iframe');

            if ($noise !== false) {
                foreach ($noise as $node) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }

            $mainNode = $xpath->query('//main|//article|//*[@id="content"]|//*[@id="main"]')->item(0);
            $rawText = $mainNode ? (string) $mainNode->textContent : (string) ($dom->textContent ?? '');

            return $this->normalizeWhitespace($rawText);
        } catch (\Throwable) {
            return $this->normalizeWhitespace(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
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
