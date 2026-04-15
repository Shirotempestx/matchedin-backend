<?php

namespace App\Http\Controllers;

use App\Services\ImportPipeline\FileExtractorAdapter;
use App\Services\ImportPipeline\UrlExtractorAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PremiumServiceController extends Controller
{
    private const GEMINI_DEFAULT_MODEL = 'gemini-2.5-flash';
    private const GEMINI_FALLBACK_MODELS = ['gemini-2.0-flash', 'gemini-1.5-flash'];
    private const WORK_MODES = ['Remote', 'Hybrid', 'On-site'];
    private const CONTRACT_TYPES = ['CDI', 'CDD', 'Stage', 'Freelance'];
    private const EDUCATION_LEVELS = ['Bac', 'Bac+2', 'Bac+3', 'Bac+5', 'Bac+8'];

    public function __construct(
        private readonly FileExtractorAdapter $fileExtractorAdapter,
        private readonly UrlExtractorAdapter $urlExtractorAdapter
    ) {
    }

    private function premiumHttp(int $timeout = 25)
    {
        $http = Http::timeout($timeout);
        $verifySsl = filter_var((string) env('PREMIUM_HTTP_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN);

        if (!$verifySsl) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }

    private function enforcePremiumEnterprise(Request $request)
    {
        if ($request->user()->role !== 'enterprise') {
            return response()->json(['message' => __('messages.unauthorized_action')], 403);
        }

        if (empty($request->user()->subscription_tier)) {
            return response()->json(['message' => 'Premium subscription required.'], 403);
        }

        return null;
    }

    /**
     * AI Assistant for writing job descriptions.
     */
    public function aiWrite(Request $request)
    {
        $premiumGuard = $this->enforcePremiumEnterprise($request);
        if ($premiumGuard) {
            return $premiumGuard;
        }

        $request->validate([
            'prompt' => 'required|string|max:2000',
            'rewrite_mode' => 'nullable|in:generate,enhance',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:4000',
            'location' => 'nullable|string|max:255',
            'contract_type' => 'nullable|in:CDI,CDD,Stage,Freelance',
            'work_mode' => 'nullable|in:Remote,Hybrid,On-site',
            'skills_required' => 'nullable|array',
            'skills_required.*.id' => 'nullable|integer',
            'skills_required.*.level' => 'nullable|integer|min:1|max:5',
            'salary_min' => 'nullable|integer|min:0',
            'salary_max' => 'nullable|integer|min:0',
            'internship_period' => 'nullable|integer|min:1',
            'niveau_etude' => 'nullable|string|max:255',
            'places_demanded' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $geminiApiKey = env('GEMINI_API_KEY');
        if (!$geminiApiKey) {
            return response()->json(['error' => 'AI provider not configured.'], 503);
        }

        $rewriteMode = (string) $request->input('rewrite_mode', 'generate');
        $hasExistingContent = trim((string) $request->input('title', '')) !== '' || trim((string) $request->input('description', '')) !== '';

        $systemPrompt = "You are an HR writing assistant for MatchendIN. "
            . "Task mode: {$rewriteMode}. "
            . "If mode is enhance and existing title/description are provided, improve them in a professional recruiting tone while preserving role intent and key facts. "
            . "If mode is generate, create fresh content from user prompt and optional context. "
            . "Keep the source language unless explicitly asked to translate. "
            . "Return strict JSON only with this exact shape: "
            . "{\"title\": string|null, \"description\": string|null, \"skills\": string[], \"location\": string|null, \"work_mode\": \"Remote\"|\"Hybrid\"|\"On-site\"|null, \"contract_type\": \"CDI\"|\"CDD\"|\"Stage\"|\"Freelance\"|null, \"salary_min\": number|null, \"salary_max\": number|null, \"internship_period\": number|null, \"niveau_etude\": \"Bac\"|\"Bac+2\"|\"Bac+3\"|\"Bac+5\"|\"Bac+8\"|null, \"places_demanded\": number|null, \"start_date\": \"YYYY-MM-DD\"|null, \"end_date\": \"YYYY-MM-DD\"|null}. "
            . "No markdown fences. No prose. No extra keys. Values must be plain text values, not nested JSON strings.";

        $contextText = [
            'User prompt: ' . $request->input('prompt'),
            'Current title: ' . ($request->input('title') ?? ''),
            'Current description: ' . ($request->input('description') ?? ''),
            'Has existing content: ' . ($hasExistingContent ? 'yes' : 'no'),
            'Location: ' . ($request->input('location') ?? ''),
            'Contract type: ' . ($request->input('contract_type') ?? ''),
            'Work mode: ' . ($request->input('work_mode') ?? ''),
            'Current skills IDs: ' . json_encode($request->input('skills_required', [])),
            'Salary min: ' . ($request->input('salary_min') ?? ''),
            'Salary max: ' . ($request->input('salary_max') ?? ''),
            'Internship period (months): ' . ($request->input('internship_period') ?? ''),
            'Education level: ' . ($request->input('niveau_etude') ?? ''),
            'Places demanded: ' . ($request->input('places_demanded') ?? ''),
            'Start date: ' . ($request->input('start_date') ?? ''),
            'End date: ' . ($request->input('end_date') ?? ''),
        ];

        try {
            $response = $this->callGeminiWithFallback($geminiApiKey, [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [[
                    'parts' => [[
                        'text' => implode("\n", $contextText),
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.5,
                    'maxOutputTokens' => 1200,
                    'responseMimeType' => 'application/json',
                ],
            ], $this->geminiTimeoutSeconds());

            if (!$response || !$response->successful()) {
                return response()->json([
                    'error' => $this->resolveGeminiErrorMessage($response, 'AI Service currently unavailable.'),
                ], 503);
            }

            $textContent = (string) Arr::get($response->json(), 'candidates.0.content.parts.0.text', '');
            $decoded = $this->decodeJsonPayload($textContent) ?? [];

            $freeText = trim($textContent);
            $safeDescriptionFallback = $this->looksLikeJson($freeText) ? '' : $freeText;

            $fallback = [
                'title' => (string) $request->input('title', ''),
                'description' => $safeDescriptionFallback !== '' ? $safeDescriptionFallback : (string) $request->input('description', ''),
                'location' => (string) $request->input('location', ''),
                'work_mode' => $request->input('work_mode'),
                'contract_type' => $request->input('contract_type'),
                'salary_min' => $request->input('salary_min'),
                'salary_max' => $request->input('salary_max'),
                'internship_period' => $request->input('internship_period'),
                'niveau_etude' => $request->input('niveau_etude'),
                'places_demanded' => $request->input('places_demanded'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
            ];

            $parsedOffer = $this->normalizeOfferPayload($this->mergeOfferData($fallback, $decoded));

            return response()->json([
                'title' => $parsedOffer['title'],
                'description' => $parsedOffer['description'],
                'skills' => $parsedOffer['skills'],
                'parsed_offer' => $parsedOffer,
                'raw' => $decoded,
            ]);
        } catch (\Exception $e) {
            Log::error('Gemini integration failed: ' . $e->getMessage());
        }

        return response()->json(['error' => 'AI Service currently unavailable.'], 503);
    }

    /**
     * Extract data from uploaded files using the neural-extractor API.
     */
    public function extract(Request $request)
    {
        $premiumGuard = $this->enforcePremiumEnterprise($request);
        if ($premiumGuard) {
            return $premiumGuard;
        }

        $request->validate([
            'files' => 'required',
            'files.*' => 'file|mimes:pdf,png,jpg,jpeg,webp,tiff,gif,bmp,doc,docx,xls,xlsx,csv,tsv,ppt,pptx,html,htm|max:51200',
        ]);

        try {
            $files = $request->file('files');
            if (!is_array($files)) {
                $files = [$files];
            }

            $rawText = $this->fileExtractorAdapter->extractRawText($files);
            return response()->json([
                'source' => 'document',
                'extractor' => null,
                'parsed_offer' => $this->parseOfferFromRawText($rawText),
            ]);
        } catch (\Exception $e) {
            Log::error('Extraction error: ' . $e->getMessage());
        }

        return response()->json(['error' => 'Data extraction service unavailable.'], 503);
    }

    /**
     * Extract data from a website URL through the neural-extractor API.
     */
    public function extractUrl(Request $request)
    {
        $premiumGuard = $this->enforcePremiumEnterprise($request);
        if ($premiumGuard) {
            return $premiumGuard;
        }

        $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        try {
            $url = (string) $request->input('url');
            $rawText = $this->urlExtractorAdapter->extractRawText($url);

            return response()->json([
                'source' => 'url',
                'url' => $url,
                'extractor' => null,
                'parsed_offer' => $this->parseOfferFromRawText($rawText),
            ]);
        } catch (\Exception $e) {
            Log::error('URL extraction error: ' . $e->getMessage());
            return response()->json(['error' => 'Data extraction service unavailable.'], 503);
        }
    }
    private function parseOfferFromRawText(string $rawText): array
    {
        $fullText = trim($rawText);

        if ($fullText === '') {
            $empty = $this->normalizeOfferPayload([]);
            $empty['chunks'] = [];
            return $empty;
        }

        $lines = preg_split('/\R/u', $fullText) ?: [];
        $headingText = '';
        foreach ($lines as $line) {
            $candidate = trim((string) $line);
            if ($candidate !== '') {
                $headingText = $candidate;
                break;
            }
        }

        $description = trim(Str::limit($fullText, 3800, ''));

        preg_match_all('/\b(?:React|Node(?:\.js)?|Laravel|PHP|TypeScript|JavaScript|Python|Docker|Kubernetes|SQL|PostgreSQL|MySQL|AWS|Azure|GCP|Figma|SEO|Excel|Power BI|Scrum|Agile|Salesforce|HubSpot)\b/i', $fullText, $matches);
        $skills = collect($matches[0] ?? [])->map(fn($item) => trim((string) $item))->filter()->unique()->values()->take(12)->all();

        $heuristic = [
            'title' => $headingText !== '' ? $headingText : null,
            'description' => $description !== '' ? $description : null,
            'skills' => $skills,
        ];

        $classified = $this->classifyOfferFromText($fullText, $headingText !== '' ? $headingText : null);
        $normalized = $this->normalizeOfferPayload($this->mergeOfferData($heuristic, $classified ?? []));
        $normalized['chunks'] = [];

        return $normalized;
    }


    public function translate(Request $request)
    {
        $premiumGuard = $this->enforcePremiumEnterprise($request);
        if ($premiumGuard) {
            return $premiumGuard;
        }

        $request->validate([
            'text' => 'required|string|max:8000',
            'target_language' => ['required', 'string', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
        ]);

        $geminiApiKey = env('GEMINI_API_KEY');
        if (!$geminiApiKey) {
            return response()->json(['error' => 'AI provider not configured.'], 503);
        }

        $targetLanguage = (string) $request->input('target_language');
        $text = (string) $request->input('text');
        $text = trim($text);

        if ($text === '') {
            return response()->json(['text' => '']);
        }

        try {
            $response = $this->callGeminiWithFallback($geminiApiKey, [
                'systemInstruction' => [
                    'parts' => [[
                        'text' => 'You are a professional translator. Translate only the provided text. Preserve meaning and recruitment tone. Return strict JSON: {"text": string}. No markdown fences, no extra keys.',
                    ]],
                ],
                'contents' => [[
                    'parts' => [[
                        'text' => "Target language: {$targetLanguage}\nText:\n{$text}",
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1400,
                    'responseMimeType' => 'application/json',
                ],
            ], $this->geminiTimeoutSeconds());

            if (!$response || !$response->successful()) {
                $fallbackTranslated = $this->translateWithPublicFallback($text, $targetLanguage);
                if ($fallbackTranslated !== null) {
                    return response()->json([
                        'text' => $fallbackTranslated,
                        'provider' => 'fallback',
                    ]);
                }

                return response()->json([
                    'text' => $text,
                    'warning' => $this->resolveGeminiErrorMessage($response, 'Translation service currently unavailable. Original text returned.'),
                ]);
            }

            $textContent = (string) Arr::get($response->json(), 'candidates.0.content.parts.0.text', '');
            $decoded = json_decode($textContent, true);
            $translated = is_array($decoded) ? trim((string) ($decoded['text'] ?? '')) : trim($textContent);

            if ($translated === '') {
                $fallbackTranslated = $this->translateWithPublicFallback($text, $targetLanguage);
                if ($fallbackTranslated !== null) {
                    return response()->json([
                        'text' => $fallbackTranslated,
                        'provider' => 'fallback',
                    ]);
                }

                return response()->json([
                    'text' => $text,
                    'warning' => 'Translation service currently unavailable. Original text returned.',
                ]);
            }

            return response()->json(['text' => $translated]);
        } catch (\Exception $e) {
            Log::error('Gemini translation failed: ' . $e->getMessage());
        }

        $fallbackTranslated = $this->translateWithPublicFallback($text, $targetLanguage);
        if ($fallbackTranslated !== null) {
            return response()->json([
                'text' => $fallbackTranslated,
                'provider' => 'fallback',
            ]);
        }

        return response()->json([
            'text' => $text,
            'warning' => 'Translation service currently unavailable. Original text returned.',
        ]);
    }

    private function wrapWebsiteContentForExtraction(string $url, string $content, string $contentType = ''): string
    {
        $trimmedContent = trim($content);

        if ($trimmedContent === '') {
            return '<html><body><p>Empty content retrieved for ' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
        }

        if (Str::contains(strtolower($contentType), 'html') || Str::contains(strtolower($trimmedContent), '<html')) {
            return $trimmedContent;
        }

        return '<html><head><meta charset="utf-8"><title>' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</title></head><body><pre style="white-space: pre-wrap; font-family: Arial, sans-serif;">' . htmlspecialchars($trimmedContent, ENT_QUOTES, 'UTF-8') . '</pre></body></html>';
    }

    private function parseOfferFromExtractorResults(array $payload): array
    {
        $results = collect($payload['results'] ?? []);
        $firstSuccess = $results->first(fn($item) => ($item['success'] ?? false) === true);

        if (!$firstSuccess) {
            $empty = $this->normalizeOfferPayload([]);
            $empty['chunks'] = [];
            return $empty;
        }

        $chunks = collect($firstSuccess['document']['chunks'] ?? []);
        $heading = $chunks->first(fn($chunk) => ($chunk['type'] ?? '') === 'heading' && !empty($chunk['text']));
        $paragraphs = $chunks
            ->filter(fn($chunk) => in_array($chunk['type'] ?? '', ['paragraph', 'list', 'table', 'heading'], true))
            ->pluck('text')
            ->filter(fn($text) => is_string($text) && trim($text) !== '');

        $fullText = $paragraphs->implode("\n");
        $description = trim(Str::limit($fullText, 3800, ''));
        $headingText = trim((string) ($heading['text'] ?? ''));

        preg_match_all('/\b(?:React|Node(?:\.js)?|Laravel|PHP|TypeScript|JavaScript|Python|Docker|Kubernetes|SQL|PostgreSQL|MySQL|AWS|Azure|GCP|Figma|SEO|Excel|Power BI|Scrum|Agile|Salesforce|HubSpot)\b/i', $fullText, $matches);
        $skills = collect($matches[0] ?? [])->map(fn($item) => trim((string) $item))->filter()->unique()->values()->take(12)->all();

        $heuristic = [
            'title' => $headingText !== '' ? $headingText : null,
            'description' => $description !== '' ? $description : null,
            'skills' => $skills,
        ];

        $classified = $this->classifyOfferFromText($fullText, $headingText !== '' ? $headingText : null);
        $normalized = $this->normalizeOfferPayload($this->mergeOfferData($heuristic, $classified ?? []));
        $normalized['chunks'] = $chunks->take(60)->values()->all();

        return $normalized;
    }

    private function classifyOfferFromText(string $fullText, ?string $heading = null): ?array
    {
        $geminiApiKey = env('GEMINI_API_KEY');
        if (!$geminiApiKey || trim($fullText) === '') {
            return null;
        }

        $trimmedText = Str::limit($fullText, 12000, '');
        $headingText = $heading ? "Heading hint: {$heading}\n" : '';

        $systemPrompt = 'You classify recruitment content into structured offer fields. Keep source language unless told otherwise. Return strict JSON only with this exact shape: {"title": string|null, "description": string|null, "skills": string[], "location": string|null, "work_mode": "Remote"|"Hybrid"|"On-site"|null, "contract_type": "CDI"|"CDD"|"Stage"|"Freelance"|null, "salary_min": number|null, "salary_max": number|null, "internship_period": number|null, "niveau_etude": "Bac"|"Bac+2"|"Bac+3"|"Bac+5"|"Bac+8"|null, "places_demanded": number|null, "start_date": "YYYY-MM-DD"|null, "end_date": "YYYY-MM-DD"|null}. No markdown fences. No extra keys.';

        try {
            $response = $this->callGeminiWithFallback($geminiApiKey, [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [[
                    'parts' => [[
                        'text' => $headingText . "Classify this extracted offer content:\n{$trimmedText}",
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1400,
                    'responseMimeType' => 'application/json',
                ],
            ], $this->geminiTimeoutSeconds());

            if (!$response || !$response->successful()) {
                return null;
            }

            $textContent = (string) Arr::get($response->json(), 'candidates.0.content.parts.0.text', '');
            $decoded = json_decode($textContent, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Exception $e) {
            Log::warning('Gemini classify exception: ' . $e->getMessage());
            return null;
        }
    }

    private function mergeOfferData(array $base, array $incoming): array
    {
        $merged = $base;

        foreach ($incoming as $key => $value) {
            if (!array_key_exists($key, $base)) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            if (is_array($value) && empty($value)) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    private function normalizeOfferPayload(array $offer): array
    {
        $normalized = [
            'title' => $this->normalizeNullableString($offer['title'] ?? null, 255),
            'description' => $this->normalizeNullableString($offer['description'] ?? null, 3900),
            'skills' => $this->normalizeSkills($offer['skills'] ?? []),
            'location' => $this->normalizeNullableString($offer['location'] ?? null, 255),
            'work_mode' => $this->normalizeEnum($offer['work_mode'] ?? null, self::WORK_MODES),
            'contract_type' => $this->normalizeEnum($offer['contract_type'] ?? null, self::CONTRACT_TYPES),
            'salary_min' => $this->normalizeNullableInt($offer['salary_min'] ?? null, 0),
            'salary_max' => $this->normalizeNullableInt($offer['salary_max'] ?? null, 0),
            'internship_period' => $this->normalizeNullableInt($offer['internship_period'] ?? null, 1),
            'niveau_etude' => $this->normalizeEnum($offer['niveau_etude'] ?? null, self::EDUCATION_LEVELS),
            'places_demanded' => $this->normalizeNullableInt($offer['places_demanded'] ?? null, 1),
            'start_date' => $this->normalizeNullableDate($offer['start_date'] ?? null),
            'end_date' => $this->normalizeNullableDate($offer['end_date'] ?? null),
        ];

        if (
            $normalized['salary_min'] !== null &&
            $normalized['salary_max'] !== null &&
            $normalized['salary_min'] > $normalized['salary_max']
        ) {
            $tmp = $normalized['salary_min'];
            $normalized['salary_min'] = $normalized['salary_max'];
            $normalized['salary_max'] = $tmp;
        }

        return $normalized;
    }

    private function normalizeNullableString($value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return Str::limit($trimmed, $maxLength, '');
    }

    private function normalizeEnum($value, array $allowed): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        foreach ($allowed as $item) {
            if (Str::lower($trimmed) === Str::lower($item)) {
                return $item;
            }
        }

        return null;
    }

    private function normalizeNullableInt($value, int $min): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;
        return $intValue >= $min ? $intValue : null;
    }

    private function normalizeNullableDate($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $trimmed);
        return $date && $date->format('Y-m-d') === $trimmed ? $trimmed : null;
    }

    private function normalizeSkills($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function ($item) {
                if (is_string($item)) {
                    return trim($item);
                }

                if (is_array($item) && isset($item['name']) && is_string($item['name'])) {
                    return trim($item['name']);
                }

                return '';
            })
            ->filter(fn($item) => $item !== '')
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    private function geminiTimeoutSeconds(): int
    {
        $configured = (int) env('GEMINI_TIMEOUT_SECONDS', 25);
        return max(8, min($configured, 40));
    }

    private function translateWithPublicFallback(string $text, string $targetLanguage): ?string
    {
        try {
            $response = $this->premiumHttp(20)->get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => 'auto',
                'tl' => $targetLanguage,
                'dt' => 't',
                'q' => $text,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $payload = $response->json();
            if (!is_array($payload) || !isset($payload[0]) || !is_array($payload[0])) {
                return null;
            }

            $translated = collect($payload[0])
                ->map(function ($item): string {
                    if (!is_array($item)) {
                        return '';
                    }

                    $segment = $item[0] ?? null;
                    return is_string($segment) ? $segment : '';
                })
                ->implode('');

            $translated = trim($translated);
            return $translated !== '' ? $translated : null;
        } catch (\Throwable $e) {
            Log::warning('Public translation fallback failed: ' . $e->getMessage());
            return null;
        }
    }

    private function geminiModelCandidates(): array
    {
        $primary = (string) env('GEMINI_MODEL', self::GEMINI_DEFAULT_MODEL);
        $fallbackCsv = (string) env('GEMINI_FALLBACK_MODELS', implode(',', self::GEMINI_FALLBACK_MODELS));

        $all = collect([$primary])
            ->merge(explode(',', $fallbackCsv))
            ->map(fn($item) => trim((string) $item))
            ->filter(fn($item) => $item !== '')
            ->unique()
            ->values()
            ->all();

        return !empty($all) ? $all : [self::GEMINI_DEFAULT_MODEL];
    }

    private function callGeminiWithFallback(string $apiKey, array $payload, int $timeoutSeconds)
    {
        $models = $this->geminiModelCandidates();
        $maxAttempts = max(1, min((int) env('GEMINI_TOTAL_ATTEMPTS', 3), 6));
        $lastResponse = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $model = $models[($attempt - 1) % count($models)];

            try {
                $response = $this->premiumHttp($timeoutSeconds)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", $payload);
            } catch (\Exception $e) {
                Log::warning('Gemini request exception', [
                    'model' => $model,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'message' => $e->getMessage(),
                ]);

                if ($attempt < $maxAttempts) {
                    usleep(200000 * $attempt);
                }

                continue;
            }

            $lastResponse = $response;

            if ($response->successful()) {
                return $response;
            }

            $status = $response->status();
            $retryable = in_array($status, [429, 500, 502, 503, 504], true);

            Log::warning('Gemini request failed', [
                'model' => $model,
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
                'status' => $status,
                'retryable' => $retryable,
                'body' => Str::limit((string) $response->body(), 1200, ''),
            ]);

            if (!$retryable) {
                break;
            }

            if ($attempt < $maxAttempts) {
                usleep(250000 * $attempt);
            }
        }

        return $lastResponse;
    }

    private function resolveGeminiErrorMessage($response, string $fallback): string
    {
        if (!$response) {
            return $fallback;
        }

        $status = method_exists($response, 'status') ? (int) $response->status() : 0;
        $providerMessage = trim((string) Arr::get($response->json(), 'error.message', ''));
        $providerLower = Str::lower($providerMessage);

        if ($status === 429 || Str::contains($providerLower, ['rate limit', 'quota'])) {
            return 'AI service is rate-limited right now. Please retry in a few moments.';
        }

        if ($status === 503 || Str::contains($providerLower, ['high demand', 'unavailable'])) {
            return 'AI service is temporarily busy. Please retry in a few moments.';
        }

        return $fallback;
    }

    private function decodeJsonPayload(string $text): ?array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $withoutFence = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $trimmed);
        if (is_string($withoutFence)) {
            $decoded = json_decode(trim($withoutFence), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $objectText = $this->extractFirstJsonObject($trimmed);
        if ($objectText !== null) {
            $decoded = json_decode($objectText, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function extractFirstJsonObject(string $text): ?string
    {
        $length = strlen($text);
        $start = -1;
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
                continue;
            }

            if ($char === '}') {
                if ($depth > 0) {
                    $depth--;
                    if ($depth === 0 && $start >= 0) {
                        return substr($text, $start, $i - $start + 1);
                    }
                }
            }
        }

        return null;
    }

    private function looksLikeJson(string $text): bool
    {
        $trimmed = ltrim($text);
        return Str::startsWith($trimmed, '{') || Str::startsWith($trimmed, '[') || Str::startsWith($trimmed, '```');
    }
}
