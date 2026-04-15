<?php

namespace App\Services\ImportPipeline;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GeminiClassificationEngine implements AIClassificationEngine
{
    private const GEMINI_DEFAULT_MODEL = 'gemini-2.5-flash';
    private const OFFER_EMPLOYMENT_TYPES = ['Full-time', 'Part-time', 'Internship', 'Freelance'];
    private const OFFER_WORK_MODES = ['Remote', 'Hybrid', 'On-site'];
    private const OFFER_NIVEAU_ETUDES = ['Bac', 'Bac+2', 'Bac+3', 'Bac+5', 'Bac+8'];
    private const PROFILE_TYPES = ['IT', 'NON_IT'];
    private const PROFILE_LANGUAGES = ['fr', 'en'];

    private const OFFER_SYSTEM_PROMPT = 'You are a strict JSON-only parser for recruitment content. '
        . 'Your task is to classify and standardize raw job text into one JSON object with EXACTLY these keys and no others: '
        . '{"title": "String", "company": "String | null", "location": "String | null", "employmentType": "Enum(Full-time, Part-time, Internship, Freelance) | null", "workMode": "Enum(Remote, Hybrid, On-site) | null", "skillsRequired": ["Array of Strings"], "description": "String", "experienceLevel": "String | null", "salaryMin": "Integer | null", "salaryMax": "Integer | null", "internshipPeriod": "Integer(months) | null", "niveauEtude": "Enum(Bac, Bac+2, Bac+3, Bac+5, Bac+8) | null", "placesDemanded": "Integer >= 1 | null", "startDate": "YYYY-MM-DD | null", "endDate": "YYYY-MM-DD | null"}. '
        . 'Rules: output must be valid JSON object only; no markdown, no prose, no code fences; never return additional keys; if value is unknown use null; description must be cleaned readable text focused on role mission and responsibilities only; do not include salutations, candidate names, addresses, emails, legal letter intros, or compensation/benefits sections; skillsRequired must contain unique strings only; salaryMin/salaryMax must be integers without currency symbols; if both dates exist endDate must be on/after startDate.';

    private const PROFILE_SYSTEM_PROMPT = "You are a strict data extraction AI processing a candidate's CV.\n"
        . "Output ONLY a valid JSON object matching this exact schema:\n"
        . "{\n"
        . "  \"headline\": \"String (Only the main Role/Title, e.g. 'Software Engineer'). DO NOT include names, locations, or URLs here. | null\",\n"
        . "  \"city\": \"String (Only the City or Country name, e.g. 'Paris'). | null\",\n"
        . "  \"availability\": \"String | null\",\n"
        . "  \"workMode\": \"Enum(Remote, Hybrid, On-site) | null\",\n"
        . "  \"bio\": \"String (A short 2-3 sentence professional summary). | null\",\n"
        . "  \"githubUrl\": \"String (A valid GitHub URL, starting with http). | null\",\n"
        . "  \"linkedinUrl\": \"String (A valid LinkedIn URL, starting with http). | null\",\n"
        . "  \"portfolioUrl\": \"String (A valid website URL). | null\",\n"
        . "  \"cvUrl\": \"String | null\",\n"
        . "  \"profileType\": \"Enum(IT, NON_IT) | null\",\n"
        . "  \"preferredLanguage\": \"Enum(fr, en) | null\",\n"
        . "  \"skills\": [\"Array of Strings (e.g., ['JavaScript', 'Python'])\"]\n"
        . "}\n"
        . "CRITICAL INSTRUCTIONS:\n"
        . "1. NEVER combine fields. Separate data precisely into their specific keys.\n"
        . "2. 'headline' MUST ONLY contain the job title or profession. NOT the candidate's name or URLs (leave null if no clear title exists).\n"
        . "3. 'city' MUST ONLY contain the geographical location.\n"
        . "4. If the input text is just an error message, an empty document, or an anti-bot captcha page, return ALL string fields as null and empty arrays.\n"
        . "5. 'bio' MUST NOT dump the entire CV text. Synthesize a short summary instead.\n"
        . "6. Output must be a raw JSON object string. NO markdown fences like ```json.";

    /**
     * @return array{payload: array<string, mixed>, warnings: array<int, string>, retry_count: int}
     */
    public function classifyOffer(string $rawText): array
    {
        return $this->classifyWithRetry('offer', self::OFFER_SYSTEM_PROMPT, $rawText, 2);
    }

    /**
     * @return array{payload: array<string, mixed>, warnings: array<int, string>, retry_count: int}
     */
    public function classifyStudentProfile(string $rawText): array
    {
        return $this->classifyWithRetry('profile', self::PROFILE_SYSTEM_PROMPT, $rawText, 2);
    }

    /**
     * @return array{payload: array<string, mixed>, warnings: array<int, string>, retry_count: int}
     */
    private function classifyWithRetry(string $entity, string $systemPrompt, string $rawText, int $maxAttempts): array
    {
        $trimmed = trim($rawText);
        if ($trimmed === '') {
            throw new RuntimeException('Cannot classify empty text.');
        }

        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            throw new RuntimeException('AI provider not configured.');
        }

        $warnings = [];
        $attempt = 1;
        $feedback = '';

        while ($attempt <= $maxAttempts) {
            $userPrompt = $this->buildUserPrompt($trimmed, $feedback);
            $responseText = $this->askGemini($apiKey, $systemPrompt, $userPrompt);

            $decoded = $this->decodeJsonPayload($responseText);
            if (!is_array($decoded)) {
                $feedback = 'Previous output was not valid JSON. Return a single valid JSON object only.';
                $warnings[] = "Attempt {$attempt}: malformed JSON from model.";
                $attempt++;
                continue;
            }

            if ($entity === 'offer') {
                [$sanitized, $entityWarnings, $isValid] = $this->sanitizeOfferPayload($decoded);
            } else {
                [$sanitized, $entityWarnings, $isValid] = $this->sanitizeProfilePayload($decoded);
            }

            $warnings = [...$warnings, ...$entityWarnings];

            if ($isValid) {
                return [
                    'payload' => $sanitized,
                    'warnings' => $warnings,
                    'retry_count' => max(0, $attempt - 1),
                ];
            }

            $feedback = 'Previous output violated schema constraints. Return only valid keys and valid enum values.';
            $attempt++;
        }

        throw new RuntimeException('AI classification failed after retry.');
    }

    private function buildUserPrompt(string $rawText, string $feedback): string
    {
        $maxChars = max(2000, (int) env('IMPORT_PIPELINE_MAX_CHARS', 12000));
        $content = Str::limit($rawText, $maxChars, '');

        $parts = [];
        if ($feedback !== '') {
            $parts[] = "Validation feedback: {$feedback}";
        }

        $parts[] = "Raw text to classify:\n{$content}";

        return implode("\n\n", $parts);
    }

    private function askGemini(string $apiKey, string $systemPrompt, string $userPrompt): string
    {
        $model = (string) env('GEMINI_MODEL', self::GEMINI_DEFAULT_MODEL);
        $request = Http::timeout((int) env('GEMINI_TIMEOUT_SECONDS', 25));
        $verifySsl = filter_var((string) env('PREMIUM_HTTP_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN);

        if (!$verifySsl) {
            $request = $request->withoutVerifying();
        }

        $response = $request->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [[
                'parts' => [['text' => $userPrompt]],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 1500,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('AI classification service unavailable.');
        }

        return (string) Arr::get($response->json(), 'candidates.0.content.parts.0.text', '');
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

    /**
     * @param array<string, mixed> $payload
     * @return array{0: array<string, mixed>, 1: array<int, string>, 2: bool}
     */
    private function sanitizeOfferPayload(array $payload): array
    {
        $allowed = [
            'title',
            'company',
            'location',
            'employmentType',
            'workMode',
            'skillsRequired',
            'description',
            'experienceLevel',
            'salaryMin',
            'salaryMax',
            'internshipPeriod',
            'niveauEtude',
            'placesDemanded',
            'startDate',
            'endDate',
        ];

        $warnings = [];
        $unknown = array_diff(array_keys($payload), $allowed);
        if (count($unknown) > 0) {
            $warnings[] = 'Unknown keys removed: ' . implode(', ', $unknown);
        }

        $employment = $this->normalizeEnum($payload['employmentType'] ?? null, self::OFFER_EMPLOYMENT_TYPES);
        $workMode = $this->normalizeEnum($payload['workMode'] ?? null, self::OFFER_WORK_MODES);
        $skills = $this->normalizeSkillArray($payload['skillsRequired'] ?? null);
        $salaryMin = $this->normalizeNullableInteger($payload['salaryMin'] ?? null, 0);
        $salaryMax = $this->normalizeNullableInteger($payload['salaryMax'] ?? null, 0);
        $internshipPeriod = $this->normalizeNullableInteger($payload['internshipPeriod'] ?? null, 1);
        $placesDemanded = $this->normalizeNullableInteger($payload['placesDemanded'] ?? null, 1);
        $startDate = $this->normalizeDate($payload['startDate'] ?? null);
        $endDate = $this->normalizeDate($payload['endDate'] ?? null);

        if ($salaryMin !== null && $salaryMax !== null && $salaryMin > $salaryMax) {
            [$salaryMin, $salaryMax] = [$salaryMax, $salaryMin];
        }

        if ($startDate !== null && $endDate !== null && strcmp($endDate, $startDate) < 0) {
            $endDate = null;
            $warnings[] = 'endDate was before startDate and has been cleared.';
        }

        $sanitized = [
            'title' => $this->normalizeNullableString($payload['title'] ?? null, 255),
            'company' => $this->normalizeNullableString($payload['company'] ?? null, 255),
            'location' => $this->normalizeNullableString($payload['location'] ?? null, 255),
            'employmentType' => $employment,
            'workMode' => $workMode,
            'skillsRequired' => $skills,
            'description' => $this->cleanOfferDescription($payload['description'] ?? null),
            'experienceLevel' => $this->normalizeNullableString($payload['experienceLevel'] ?? null, 120),
            'salaryMin' => $salaryMin,
            'salaryMax' => $salaryMax,
            'internshipPeriod' => $internshipPeriod,
            'niveauEtude' => $this->normalizeEnum($payload['niveauEtude'] ?? null, self::OFFER_NIVEAU_ETUDES),
            'placesDemanded' => $placesDemanded,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        if ($sanitized['description'] === null) {
            $warnings[] = 'description is missing or empty.';
        }

        $isValid = $sanitized['description'] !== null;

        return [$sanitized, $warnings, $isValid];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: array<string, mixed>, 1: array<int, string>, 2: bool}
     */
    private function sanitizeProfilePayload(array $payload): array
    {
        $allowed = [
            'headline',
            'city',
            'availability',
            'workMode',
            'bio',
            'githubUrl',
            'linkedinUrl',
            'portfolioUrl',
            'cvUrl',
            'profileType',
            'preferredLanguage',
            'skills',
        ];

        $warnings = [];
        $unknown = array_diff(array_keys($payload), $allowed);
        if (count($unknown) > 0) {
            $warnings[] = 'Unknown keys removed: ' . implode(', ', $unknown);
        }

        $sanitized = [
            'headline' => $this->normalizeNullableString($payload['headline'] ?? null, 150),
            'city' => $this->normalizeNullableString($payload['city'] ?? null, 100),
            'availability' => $this->normalizeNullableString($payload['availability'] ?? null, 120),
            'workMode' => $this->normalizeNullableString($payload['workMode'] ?? null, 60),
            'bio' => $this->normalizeNullableString($payload['bio'] ?? null, 3900),
            'githubUrl' => $this->normalizeNullableString($payload['githubUrl'] ?? null, 255),
            'linkedinUrl' => $this->normalizeNullableString($payload['linkedinUrl'] ?? null, 255),
            'portfolioUrl' => $this->normalizeNullableString($payload['portfolioUrl'] ?? null, 255),
            'cvUrl' => $this->normalizeNullableString($payload['cvUrl'] ?? null, 255),
            'profileType' => $this->normalizeEnum($payload['profileType'] ?? null, self::PROFILE_TYPES),
            'preferredLanguage' => $this->normalizeEnum($payload['preferredLanguage'] ?? null, self::PROFILE_LANGUAGES),
            'skills' => $this->normalizeSkillArray($payload['skills'] ?? null),
        ];

        if ($sanitized['bio'] === null) {
            $warnings[] = 'bio is missing or empty.';
        }

        $isValid = $sanitized['bio'] !== null;

        return [$sanitized, $warnings, $isValid];
    }

    private function normalizeNullableString(mixed $value, int $maxLength): ?string
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

    private function normalizeNullableInteger(mixed $value, int $min): ?int
    {
        if (is_int($value)) {
            return $value >= $min ? $value : null;
        }

        if (is_float($value)) {
            $int = (int) round($value);
            return $int >= $min ? $int : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (!preg_match('/-?\d[\d\s,.]*/', $trimmed, $match)) {
            return null;
        }

        $digits = preg_replace('/[^\d-]/', '', (string) ($match[0] ?? ''));
        if (!is_string($digits) || $digits === '' || $digits === '-') {
            return null;
        }

        $int = (int) $digits;
        return $int >= $min ? $int : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmed, $iso)) {
            $year = (int) $iso[1];
            $month = (int) $iso[2];
            $day = (int) $iso[3];
            return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $trimmed, $dmY)) {
            $day = (int) $dmY[1];
            $month = (int) $dmY[2];
            $year = (int) $dmY[3];
            return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
        }

        $normalizedMonthText = $this->normalizeMonthNames(Str::lower($trimmed));
        $timestamp = strtotime($normalizedMonthText);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    private function cleanOfferDescription(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $text = trim($value);
        if ($text === '') {
            return null;
        }

        $text = preg_replace('/\s*•\s*/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $text = preg_replace('/\b(?:dear|cher|chere)\b[^,.]{0,80},?/iu', ' ', $text) ?? $text;
        $text = preg_replace('/\b[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}\b/u', ' ', $text) ?? $text;

        $cutMarkers = [
            'key terms of the employment offer',
            'termes cles',
            'benefits:',
            'avantages:',
            'compensation:',
            'remuneration:',
        ];

        $lower = Str::lower($text);
        foreach ($cutMarkers as $marker) {
            $position = strpos($lower, $marker);
            if ($position !== false) {
                $text = trim((string) substr($text, 0, $position));
                break;
            }
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $noisePatterns = [
            '/offer of employment/i',
            '/we are thrilled to formally offer/i',
            '/casablanca finance city/i',
            '/\bcnops\b|\bcnss\b/i',
            '/\bhealth insurance\b/i',
            '/\bflexible working hours\b/i',
        ];

        $kept = [];
        foreach ($sentences as $sentence) {
            $candidate = trim($sentence);
            if ($candidate === '' || mb_strlen($candidate) < 20) {
                continue;
            }

            $isNoise = false;
            foreach ($noisePatterns as $pattern) {
                if (preg_match($pattern, $candidate)) {
                    $isNoise = true;
                    break;
                }
            }

            if ($isNoise) {
                continue;
            }

            $kept[] = $candidate;
            if (count($kept) >= 8) {
                break;
            }
        }

        if (count($kept) === 0) {
            return $this->normalizeNullableString($text, 2000);
        }

        $cleaned = trim(implode(' ', $kept));
        return $this->normalizeNullableString($cleaned, 2000);
    }

    private function normalizeMonthNames(string $value): string
    {
        return str_replace(
            ['janvier', 'fevrier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'août', 'septembre', 'octobre', 'novembre', 'decembre', 'décembre'],
            ['january', 'february', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'august', 'september', 'october', 'november', 'december', 'december'],
            $value
        );
    }

    /**
     * @param array<int, string> $allowed
     */
    private function normalizeEnum(mixed $value, array $allowed): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        foreach ($allowed as $allowedValue) {
            if (Str::lower($allowedValue) === Str::lower($trimmed)) {
                return $allowedValue;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeSkillArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function (mixed $item): string {
                if (is_string($item)) {
                    return trim($item);
                }

                if (is_array($item) && isset($item['name']) && is_string($item['name'])) {
                    return trim($item['name']);
                }

                return '';
            })
            ->filter(fn(string $skill): bool => $skill !== '')
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }
}
