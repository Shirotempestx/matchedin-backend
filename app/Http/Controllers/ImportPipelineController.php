<?php

namespace App\Http\Controllers;

use App\Services\ImportPipeline\AIClassificationEngine;
use App\Services\ImportPipeline\FileExtractorAdapter;
use App\Services\ImportPipeline\UrlExtractorAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ImportPipelineController extends Controller
{
    public function __construct(
        private readonly FileExtractorAdapter $fileExtractorAdapter,
        private readonly UrlExtractorAdapter $urlExtractorAdapter,
        private readonly AIClassificationEngine $classificationEngine
    ) {
    }

    public function importOffer(Request $request): JsonResponse
    {
        $premiumGuard = $this->enforcePremiumEnterprise($request);
        if ($premiumGuard !== null) {
            return $premiumGuard;
        }

        if ((string) $request->input('sourceType') === 'url') {
            $request->merge([
                'url' => $this->normalizeInputUrl($request->input('url')),
            ]);
        }

        $validated = $request->validate([
            'sourceType' => ['required', 'in:file,url,text'],
            'files' => ['required_if:sourceType,file'],
            'files.*' => ['file', 'mimes:pdf,png,jpg,jpeg,webp,tiff,gif,bmp,doc,docx,xls,xlsx,csv,tsv,ppt,pptx,html,htm', 'max:51200'],
            'url' => ['required_if:sourceType,url', 'nullable', 'url', 'max:2048'],
            'text' => ['required_if:sourceType,text', 'nullable', 'string', 'max:12000'],
        ]);

        try {
            $rawText = $this->runIngestionStage($validated, $request);
            try {
                $classification = $this->classificationEngine->classifyOffer($rawText);
            } catch (\Throwable $e) {
                $classification = $this->fallbackOfferClassification($rawText, $e->getMessage());
            }
            $allocation = $this->allocateOffer($classification['payload']);

            return response()->json([
                'pipelineVersion' => 1,
                'entity' => 'offer',
                'sourceType' => $validated['sourceType'],
                'rawTextLength' => $this->safeTextLength($rawText),
                'rawTextExcerpt' => Str::limit($rawText, 700, ''),
                'classification' => $classification,
                'allocation' => $allocation,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Import pipeline offer failed', [
                'error' => $e->getMessage(),
                'sourceType' => $validated['sourceType'] ?? null,
            ]);

            return response()->json([
                'message' => 'Import pipeline failed: ' . $e->getMessage(),
            ], 503);
        }
    }

    public function importStudentProfile(Request $request): JsonResponse
    {
        $profileGuard = $this->enforceStudentProfileAccess($request);
        if ($profileGuard !== null) {
            return $profileGuard;
        }

        if ((string) $request->input('sourceType') === 'url') {
            $request->merge([
                'url' => $this->normalizeInputUrl($request->input('url')),
            ]);
        }

        $validated = $request->validate([
            'sourceType' => ['required', 'in:file,url'],
            'files' => ['required_if:sourceType,file'],
            'files.*' => ['file', 'mimes:pdf,png,jpg,jpeg,webp,tiff,gif,bmp,doc,docx,xls,xlsx,csv,tsv,ppt,pptx,html,htm', 'max:51200'],
            'url' => ['required_if:sourceType,url', 'nullable', 'url', 'max:2048'],
        ]);

        try {
            $rawText = $this->runIngestionStage($validated, $request);
            try {
                $classification = $this->classificationEngine->classifyStudentProfile($rawText);
            } catch (\Throwable $e) {
                $classification = $this->fallbackStudentProfileClassification($rawText, $e->getMessage());
            }
            $allocation = $this->allocateStudentProfile($classification['payload']);

            return response()->json([
                'pipelineVersion' => 1,
                'entity' => 'student-profile',
                'sourceType' => $validated['sourceType'],
                'rawTextLength' => $this->safeTextLength($rawText),
                'rawTextExcerpt' => Str::limit($rawText, 700, ''),
                'classification' => $classification,
                'allocation' => $allocation,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Import pipeline student profile failed', [
                'error' => $e->getMessage(),
                'sourceType' => $validated['sourceType'] ?? null,
            ]);

            return response()->json([
                'message' => 'Import pipeline failed: ' . $e->getMessage(),
            ], 503);
        }
    }

    private function safeTextLength(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function runIngestionStage(array $validated, Request $request): string
    {
        $sourceType = (string) ($validated['sourceType'] ?? '');

        if ($sourceType === 'text') {
            $text = trim((string) ($validated['text'] ?? ''));
            if ($text === '') {
                throw new RuntimeException('Text input cannot be empty.');
            }

            return $text;
        }

        if ($sourceType === 'file') {
            $files = $request->file('files');
            if (!is_array($files)) {
                $files = $files ? [$files] : [];
            }

            return $this->fileExtractorAdapter->extractRawText($files);
        }

        return $this->urlExtractorAdapter->extractRawText((string) ($validated['url'] ?? ''));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function allocateOffer(array $payload): array
    {
        $employment = (string) ($payload['employmentType'] ?? '');
        $contractType = match ($employment) {
            'Full-time' => 'CDI',
            'Part-time' => 'CDD',
            'Internship' => 'Stage',
            'Freelance' => 'Freelance',
            default => null,
        };

        $skills = $payload['skillsRequired'] ?? [];
        if (!is_array($skills)) {
            $skills = [];
        }
        $skills = $this->normalizeSkillNames($skills);

        $workMode = $this->normalizeWorkMode($payload['workMode'] ?? null);
        $salaryMin = $this->normalizeNullableInteger($payload['salaryMin'] ?? null, 0);
        $salaryMax = $this->normalizeNullableInteger($payload['salaryMax'] ?? null, 0);
        $internshipPeriod = $this->normalizeNullableInteger($payload['internshipPeriod'] ?? null, 1);
        $niveauEtude = $this->normalizeNiveauEtude($payload['niveauEtude'] ?? null);
        $placesDemanded = $this->normalizeNullableInteger($payload['placesDemanded'] ?? null, 1);
        $startDate = $this->normalizeDate($payload['startDate'] ?? null);
        $endDate = $this->normalizeDate($payload['endDate'] ?? null);
        $title = $this->normalizeOfferTitle($payload['title'] ?? null);
        $location = $this->normalizeLocation($payload['location'] ?? null);

        if ($salaryMin !== null && $salaryMax !== null && $salaryMin > $salaryMax) {
            [$salaryMin, $salaryMax] = [$salaryMax, $salaryMin];
        }

        if ($startDate !== null && $endDate !== null && strcmp($endDate, $startDate) < 0) {
            $endDate = null;
        }

        $description = $payload['description'] ?? null;
        $cleanedDescription = is_string($description) ? $this->cleanOfferDescription($description) : null;

        return [
            'offerFormDraft' => [
                'title' => $title,
                'description' => $cleanedDescription,
                'location' => $location,
                'work_mode' => $workMode,
                'contract_type' => $contractType,
                'skills' => array_values($skills),
                'salary_min' => $salaryMin,
                'salary_max' => $salaryMax,
                'internship_period' => $internshipPeriod,
                'niveau_etude' => $niveauEtude,
                'places_demanded' => $placesDemanded,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'experience_level' => $payload['experienceLevel'] ?? null,
            ],
            'databaseDraft' => [
                'title' => $title,
                'description' => $cleanedDescription,
                'location' => $location,
                'work_mode' => $workMode,
                'contract_type' => $contractType,
                'skills_required_names' => array_values($skills),
                'salary_min' => $salaryMin,
                'salary_max' => $salaryMax,
                'internship_period' => $internshipPeriod,
                'niveau_etude' => $niveauEtude,
                'places_demanded' => $placesDemanded,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'experience_level' => $payload['experienceLevel'] ?? null,
                'company' => $payload['company'] ?? null,
                'employment_type' => $payload['employmentType'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function allocateStudentProfile(array $payload): array
    {
        $skills = $payload['skills'] ?? [];
        if (!is_array($skills)) {
            $skills = [];
        }

        return [
            'studentProfileDraft' => [
                'headline' => $payload['headline'] ?? null,
                'city' => $payload['city'] ?? null,
                'availability' => $payload['availability'] ?? null,
                'workMode' => $payload['workMode'] ?? null,
                'bio' => $payload['bio'] ?? null,
                'githubUrl' => $payload['githubUrl'] ?? null,
                'linkedinUrl' => $payload['linkedinUrl'] ?? null,
                'portfolioUrl' => $payload['portfolioUrl'] ?? null,
                'cvUrl' => $payload['cvUrl'] ?? null,
                'profile_type' => $payload['profileType'] ?? null,
                'preferred_language' => $payload['preferredLanguage'] ?? null,
                'skills' => array_values($skills),
            ],
            'databaseDraft' => [
                'title' => $payload['headline'] ?? null,
                'country' => $payload['city'] ?? null,
                'availability' => $payload['availability'] ?? null,
                'work_mode' => $payload['workMode'] ?? null,
                'bio' => $payload['bio'] ?? null,
                'website' => $payload['githubUrl'] ?? null,
                'linkedin_url' => $payload['linkedinUrl'] ?? null,
                'portfolio_url' => $payload['portfolioUrl'] ?? null,
                'cv_url' => $payload['cvUrl'] ?? null,
                'profile_type' => $payload['profileType'] ?? null,
                'preferred_language' => $payload['preferredLanguage'] ?? null,
                'skills_names' => array_values($skills),
            ],
        ];
    }

    private function enforcePremiumEnterprise(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (!$user || !$this->isEnterpriseRole($user->role ?? null)) {
            return response()->json(['message' => __('messages.unauthorized_action')], 403);
        }

        if (empty($user->subscription_tier)) {
            return response()->json(['message' => 'Premium subscription required.'], 403);
        }

        return null;
    }

    private function enforceStudentProfileAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (!$user || !$this->isStudentRole($user->role ?? null)) {
            return response()->json(['message' => __('messages.unauthorized_action')], 403);
        }

        return null;
    }

    private function normalizeInputUrl(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (!preg_match('#^https?://#i', $trimmed)) {
            return 'https://' . ltrim($trimmed, '/');
        }

        return $trimmed;
    }

    private function isStudentRole(mixed $role): bool
    {
        if (!is_string($role)) {
            return false;
        }

        $normalized = Str::lower(trim($role));
        return in_array($normalized, ['student', 'etudiant', 'étudiant'], true);
    }

    private function isEnterpriseRole(mixed $role): bool
    {
        if (!is_string($role)) {
            return false;
        }

        $normalized = Str::lower(trim($role));
        return in_array($normalized, ['enterprise', 'entreprise'], true);
    }

    /**
     * @return array{payload: array<string, mixed>, warnings: array<int, string>, retry_count: int}
     */
    private function fallbackOfferClassification(string $rawText, string $reason): array
    {
        $title = $this->extractOfferTitleFromRaw($rawText);
        $location = $this->extractLocationFromRaw($rawText);

        $description = $this->cleanOfferDescription(Str::limit($rawText, 3900, ''));

        preg_match_all('/\b(?:React|Node(?:\.js)?|Laravel|PHP|TypeScript|JavaScript|Python|Docker|Kubernetes|SQL|PostgreSQL|MySQL|AWS|Azure|GCP|Figma|SEO|Excel|Power BI|Scrum|Agile|Salesforce|HubSpot)\b/i', $rawText, $matches);
        $skills = $this->normalizeSkillNames($matches[0] ?? []);

        $employmentType = null;
        $lower = Str::lower($rawText);
        if (Str::contains($lower, ['internship', 'intern', 'stage'])) {
            $employmentType = 'Internship';
        } elseif (Str::contains($lower, ['freelance', 'contractor'])) {
            $employmentType = 'Freelance';
        } elseif (Str::contains($lower, ['part-time', 'part time'])) {
            $employmentType = 'Part-time';
        } elseif ($description !== null && $description !== '') {
            $employmentType = 'Full-time';
        }

        $workMode = $this->extractWorkModeFromRaw($rawText);
        [$salaryMin, $salaryMax] = $this->extractSalaryRangeFromRaw($rawText);
        $internshipPeriod = $this->extractInternshipPeriodFromRaw($rawText);
        $niveauEtude = $this->extractNiveauEtudeFromRaw($rawText);
        $placesDemanded = $this->extractPlacesDemandedFromRaw($rawText);
        [$startDate, $endDate] = $this->extractTimelineDatesFromRaw($rawText);

        return [
            'payload' => [
                'title' => $title,
                'company' => null,
                'location' => $location,
                'employmentType' => $employmentType,
                'workMode' => $workMode,
                'skillsRequired' => $skills,
                'description' => $description !== '' ? $description : null,
                'experienceLevel' => null,
                'salaryMin' => $salaryMin,
                'salaryMax' => $salaryMax,
                'internshipPeriod' => $internshipPeriod,
                'niveauEtude' => $niveauEtude,
                'placesDemanded' => $placesDemanded,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ],
            'warnings' => ["AI classification fallback used: {$reason}"],
            'retry_count' => 0,
        ];
    }

    private function normalizeOfferTitle(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        return $this->cleanTitleCandidate($value);
    }

    private function extractOfferTitleFromRaw(string $rawText): ?string
    {
        $patternCandidates = [
            '/(?:offer\s+of\s+employment|job\s+title|position|poste|intitul[ée]\s+du\s+poste|titre)\s*[:\-]\s*([^\n\r]{3,220})/iu',
            '/(?:role)\s*[:\-]\s*([^\n\r]{3,220})/iu',
        ];

        foreach ($patternCandidates as $pattern) {
            if (!preg_match($pattern, $rawText, $match)) {
                continue;
            }

            $cleaned = $this->cleanTitleCandidate((string) ($match[1] ?? ''));
            if ($cleaned !== null) {
                return $cleaned;
            }
        }

        $lines = preg_split('/\R/u', trim($rawText)) ?: [];
        if (count($lines) <= 1) {
            $lines = preg_split('/(?<=[.!?])\s+/u', trim($rawText)) ?: [];
        }

        foreach (array_slice($lines, 0, 20) as $line) {
            $cleaned = $this->cleanTitleCandidate((string) $line);
            if ($cleaned !== null) {
                return $cleaned;
            }
        }

        return null;
    }

    private function cleanTitleCandidate(string $value): ?string
    {
        $candidate = trim($value);
        if ($candidate === '') {
            return null;
        }

        $candidate = preg_replace('/\s+/u', ' ', $candidate) ?? $candidate;
        $candidate = preg_replace('/^(?:offer\s+of\s+employment|job\s+title|position|poste|intitul[ée]\s+du\s+poste|titre)\s*[:\-]\s*/iu', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\b(?:dear|cher|ch[èe]re|madame|monsieur|we\s+are\s+thrilled|key\s+terms|compensation|benefits|salary|salaire|in\s+this\s+role)\b.*$/iu', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B-:;,.|");

        if ($candidate === '') {
            return null;
        }

        if (preg_match('/[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}/u', $candidate)) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $candidate)) {
            return null;
        }

        if ($this->looksLikeDatePhrase($candidate)) {
            return null;
        }

        if (preg_match('/\b(?:dear|cher|ch[èe]re|madame|monsieur)\b/iu', $candidate)) {
            return null;
        }

        if (preg_match('/[.!?].*[.!?]/u', $candidate)) {
            return null;
        }

        $wordCount = count(array_values(array_filter(preg_split('/\s+/u', $candidate) ?: [])));
        if ($wordCount < 1 || $wordCount > 12) {
            return null;
        }

        if (mb_strlen($candidate) < 3 || mb_strlen($candidate) > 120) {
            return null;
        }

        return Str::limit($candidate, 120, '');
    }

    private function extractLocationFromRaw(string $rawText): ?string
    {
        if (preg_match('/(?:location|localisation|ville|lieu)\s*[:\-]\s*([^\n\r]{2,180})/iu', $rawText, $match)) {
            $location = $this->normalizeLocation($match[1] ?? null);
            if ($location !== null) {
                return $location;
            }
        }

        $lines = preg_split('/\R/u', trim($rawText)) ?: [];
        foreach (array_slice($lines, 0, 20) as $line) {
            $candidate = trim((string) $line);
            if ($candidate === '') {
                continue;
            }

            if (!preg_match('/^[A-Za-z\x{00C0}-\x{017F}][A-Za-z\x{00C0}-\x{017F} .\'\-]{0,48},\s*[A-Za-z\x{00C0}-\x{017F}][A-Za-z\x{00C0}-\x{017F} .\'\-]{1,48}$/u', $candidate)) {
                continue;
            }

            $location = $this->normalizeLocation($candidate);
            if ($location !== null) {
                return $location;
            }
        }

        return null;
    }

    private function normalizeLocation(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $candidate = trim($value);
        if ($candidate === '') {
            return null;
        }

        $candidate = preg_replace('/\s+/u', ' ', $candidate) ?? $candidate;
        $candidate = preg_replace('/^(?:location|localisation|ville|lieu)\s*[:\-]\s*/iu', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B-:;,.|");

        if ($candidate === '') {
            return null;
        }

        if (preg_match('/[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}/u', $candidate)) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $candidate)) {
            return null;
        }

        if ($this->looksLikeDatePhrase($candidate)) {
            return null;
        }

        if (preg_match('/\d{2,}/', $candidate)) {
            return null;
        }

        if (!preg_match('/[A-Za-z\x{00C0}-\x{017F}]/u', $candidate)) {
            return null;
        }

        if (mb_strlen($candidate) < 2 || mb_strlen($candidate) > 120) {
            return null;
        }

        return Str::limit($candidate, 120, '');
    }

    private function looksLikeDatePhrase(string $value): bool
    {
        if (!preg_match('/\d{1,4}/', $value)) {
            return false;
        }

        return preg_match('/\b(?:jan(?:uary|vier)?|f[ée]v(?:rier)?|feb(?:ruary)?|mar(?:s|ch)?|apr(?:il)?|avril|may|mai|jun(?:e)?|juin|jul(?:y|let)?|aug(?:ust)?|ao[uû]t|sep(?:t(?:ember)?)?|septembre|oct(?:ober|obre)?|nov(?:ember|embre)?|dec(?:ember)?|d[ée]c(?:embre)?)\b/iu', $value) === 1;
    }

    private function normalizeNullableInteger(mixed $value, int $min): ?int
    {
        if (is_int($value)) {
            return $value >= $min ? $value : null;
        }

        if (is_float($value)) {
            $rounded = (int) round($value);
            return $rounded >= $min ? $rounded : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (!preg_match('/-?\d[\d\s.,]*/', $trimmed, $match)) {
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

    private function normalizeMonthNames(string $value): string
    {
        return str_replace(
            ['janvier', 'fevrier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'août', 'septembre', 'octobre', 'novembre', 'decembre', 'décembre'],
            ['january', 'february', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'august', 'september', 'october', 'november', 'december', 'december'],
            $value
        );
    }

    private function normalizeWorkMode(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $lower = Str::lower(trim($value));
        if ($lower === '') {
            return null;
        }

        if (Str::contains($lower, ['hybrid', 'hybride'])) {
            return 'Hybrid';
        }

        if (Str::contains($lower, ['on-site', 'onsite', 'on site', 'presentiel', 'présentiel'])) {
            return 'On-site';
        }

        if (Str::contains($lower, ['remote', 'distance', 'teletravail', 'télétravail'])) {
            return 'Remote';
        }

        return null;
    }

    private function normalizeNiveauEtude(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $lower = Str::lower(trim($value));
        if ($lower === '') {
            return null;
        }

        if (Str::contains($lower, ['bac+8', 'bac +8', 'bac+7', 'bac +7', 'doctorat', 'phd'])) {
            return 'Bac+8';
        }

        if (Str::contains($lower, ['bac+5', 'bac +5', 'master', 'ingénieur', 'ingenieur'])) {
            return 'Bac+5';
        }

        if (Str::contains($lower, ['bac+3', 'bac +3', 'licence', 'bachelor'])) {
            return 'Bac+3';
        }

        if (Str::contains($lower, ['bac+2', 'bac +2', 'dut', 'bts', 'deust'])) {
            return 'Bac+2';
        }

        if (preg_match('/\bbac\b/u', $lower)) {
            return 'Bac';
        }

        return null;
    }

    private function extractWorkModeFromRaw(string $rawText): ?string
    {
        return $this->normalizeWorkMode($rawText);
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function extractSalaryRangeFromRaw(string $rawText): array
    {
        if (preg_match('/(?:salaire|salary)[^\d]{0,30}(\d[\d\s.,]{2,})\s*(?:-|–|to|a|à)\s*(\d[\d\s.,]{2,})/iu', $rawText, $range)) {
            $min = $this->normalizeNullableInteger($range[1] ?? null, 0);
            $max = $this->normalizeNullableInteger($range[2] ?? null, 0);
            if ($min !== null && $max !== null && $min > $max) {
                [$min, $max] = [$max, $min];
            }
            return [$min, $max];
        }

        if (preg_match('/(\d[\d\s.,]{2,})\s*(?:-|–|to|a|à)\s*(\d[\d\s.,]{2,})\s*(?:mad|dhs?|dh|eur|euro|usd|\$|€)/iu', $rawText, $currencyRange)) {
            $min = $this->normalizeNullableInteger($currencyRange[1] ?? null, 0);
            $max = $this->normalizeNullableInteger($currencyRange[2] ?? null, 0);
            if ($min !== null && $max !== null && $min > $max) {
                [$min, $max] = [$max, $min];
            }
            return [$min, $max];
        }

        $min = null;
        $max = null;

        if (preg_match('/(?:salaire\s*min|salary\s*min(?:imum)?)\D{0,20}(\d[\d\s.,]*)/iu', $rawText, $mMin)) {
            $min = $this->normalizeNullableInteger($mMin[1] ?? null, 0);
        }

        if (preg_match('/(?:salaire\s*max|salary\s*max(?:imum)?)\D{0,20}(\d[\d\s.,]*)/iu', $rawText, $mMax)) {
            $max = $this->normalizeNullableInteger($mMax[1] ?? null, 0);
        }

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [$min, $max];
    }

    private function extractInternshipPeriodFromRaw(string $rawText): ?int
    {
        if (preg_match('/(?:p[ée]riode\s+de\s+stage|dur[ée]e\s+du\s+stage|internship\s*(?:duration|period))\D{0,20}(\d{1,2})\s*(?:mois|month|months)/iu', $rawText, $match)) {
            return $this->normalizeNullableInteger($match[1] ?? null, 1);
        }

        return null;
    }

    private function extractNiveauEtudeFromRaw(string $rawText): ?string
    {
        return $this->normalizeNiveauEtude($rawText);
    }

    private function extractPlacesDemandedFromRaw(string $rawText): ?int
    {
        if (!preg_match('/(?:places?\s+demand[ée]es?|nombre\s+de\s+postes?|postes?\s+[àa]\s+pourvoir|openings?)\D{0,20}(\d{1,3})/iu', $rawText, $match)) {
            return null;
        }

        return $this->normalizeNullableInteger($match[1] ?? null, 1);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function extractTimelineDatesFromRaw(string $rawText): array
    {
        $startDate = null;
        $endDate = null;

        if (preg_match('/(?:start\s*date|date\s*de\s*d[ée]but|d[ée]but)\D{0,30}(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{4}|\d{4}-\d{2}-\d{2}|[A-Za-z\x{00C0}-\x{017F}]+\s+\d{1,2},\s*\d{4}|\d{1,2}\s+[A-Za-z\x{00C0}-\x{017F}]+\s+\d{4})/iu', $rawText, $start)) {
            $startDate = $this->normalizeDate($start[1] ?? null);
        }

        if (preg_match('/(?:end\s*date|date\s*de\s*fin|fin)\D{0,30}(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{4}|\d{4}-\d{2}-\d{2}|[A-Za-z\x{00C0}-\x{017F}]+\s+\d{1,2},\s*\d{4}|\d{1,2}\s+[A-Za-z\x{00C0}-\x{017F}]+\s+\d{4})/iu', $rawText, $end)) {
            $endDate = $this->normalizeDate($end[1] ?? null);
        }

        if ($startDate === null || $endDate === null) {
            $dates = [];

            preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/', $rawText, $isoDates);
            foreach (($isoDates[0] ?? []) as $isoDate) {
                $normalized = $this->normalizeDate((string) $isoDate);
                if ($normalized !== null) {
                    $dates[$normalized] = true;
                }
            }

            preg_match_all('/\b\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{4}\b/', $rawText, $dmyDates);
            foreach (($dmyDates[0] ?? []) as $dmyDate) {
                $normalized = $this->normalizeDate((string) $dmyDate);
                if ($normalized !== null) {
                    $dates[$normalized] = true;
                }
            }

            preg_match_all('/\b(?:[A-Za-z\x{00C0}-\x{017F}]+\s+\d{1,2},\s*\d{4}|\d{1,2}\s+[A-Za-z\x{00C0}-\x{017F}]+\s+\d{4})\b/u', $rawText, $textDates);
            foreach (($textDates[0] ?? []) as $textDate) {
                $normalized = $this->normalizeDate((string) $textDate);
                if ($normalized !== null) {
                    $dates[$normalized] = true;
                }
            }

            $ordered = array_keys($dates);
            sort($ordered);

            if ($startDate === null && isset($ordered[0])) {
                $startDate = $ordered[0];
            }

            if ($endDate === null && isset($ordered[1])) {
                $endDate = $ordered[1];
            }
        }

        if ($startDate !== null && $endDate !== null && strcmp($endDate, $startDate) < 0) {
            $endDate = null;
        }

        return [$startDate, $endDate];
    }

    /**
     * @return array{payload: array<string, mixed>, warnings: array<int, string>, retry_count: int}
     */
    private function fallbackStudentProfileClassification(string $rawText, string $reason): array
    {
        $lines = preg_split('/\R/u', trim($rawText)) ?: [];
        $headline = null;

        foreach ($lines as $line) {
            $candidate = trim((string) $line);
            if ($candidate !== '') {
                $headline = Str::limit($candidate, 150, '');
                break;
            }
        }

        $bio = trim(Str::limit($rawText, 3900, ''));

        preg_match_all('/\b(?:React|Node(?:\.js)?|Laravel|PHP|TypeScript|JavaScript|Python|Docker|Kubernetes|SQL|PostgreSQL|MySQL|AWS|Azure|GCP|Figma|SEO|Excel|Power BI|Scrum|Agile|Salesforce|HubSpot)\b/i', $rawText, $matches);
        $skills = $this->normalizeSkillNames($matches[0] ?? []);

        preg_match_all('/https?:\/\/[^\s]+/i', $rawText, $urlMatches);
        $urls = array_values(array_filter(array_map(fn($url) => trim((string) $url), $urlMatches[0] ?? [])));

        $githubUrl = collect($urls)->first(fn($url) => Str::contains(Str::lower($url), 'github')) ?: null;
        $linkedinUrl = collect($urls)->first(fn($url) => Str::contains(Str::lower($url), 'linkedin')) ?: null;
        $portfolioUrl = collect($urls)->first(fn($url) => $url !== $githubUrl && $url !== $linkedinUrl) ?: null;

        $profileType = count($skills) > 0 ? 'IT' : null;

        return [
            'payload' => [
                'headline' => $headline,
                'city' => null,
                'availability' => null,
                'workMode' => null,
                'bio' => $bio !== '' ? $bio : null,
                'githubUrl' => $githubUrl,
                'linkedinUrl' => $linkedinUrl,
                'portfolioUrl' => $portfolioUrl,
                'cvUrl' => null,
                'profileType' => $profileType,
                'preferredLanguage' => null,
                'skills' => $skills,
            ],
            'warnings' => ["AI classification fallback used: {$reason}"],
            'retry_count' => 0,
        ];
    }

    /**
     * @param array<int, mixed> $skills
     * @return array<int, string>
     */
    private function normalizeSkillNames(array $skills): array
    {
        $normalized = [];
        $seen = [];

        foreach ($skills as $item) {
            if (!is_string($item)) {
                continue;
            }

            $parts = preg_split('/[,;|\x{2022}\n\r]+/u', $item) ?: [];
            foreach ($parts as $part) {
                $skill = trim((string) $part);
                if ($skill === '') {
                    continue;
                }

                $skill = preg_replace('/\s+/u', ' ', $skill) ?? $skill;
                $skill = trim($skill, " \t\n\r\0\x0B-_.");
                if ($skill === '') {
                    continue;
                }

                $key = Str::lower($skill);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $normalized[] = $skill;

                if (count($normalized) >= 12) {
                    return $normalized;
                }
            }
        }

        return $normalized;
    }

    private function cleanOfferDescription(string $description): ?string
    {
        $normalizedText = preg_replace('/\s*•\s*/u', "\n", trim($description)) ?? trim($description);
        $lines = preg_split('/\R/u', $normalizedText) ?: [];
        if (count($lines) <= 1) {
            $lines = preg_split('/(?<=[.!?])\s+|(?=\b(?:dear|cher|chere|position|start\s*date|key\s*terms|benefits|compensation)\b)/iu', $normalizedText) ?: [];
        }

        $kept = [];
        $seen = [];

        $noisePatterns = [
            '/^dear\b/i',
            '/^cher\b/i',
            '/offer of employment/i',
            '/we are thrilled to formally offer/i',
            '/^key terms of the employment offer\b/i',
            '/^compensation\b/i',
            '/^benefits\b/i',
            '/\bcnops\b|\bcnss\b/i',
            '/\brecrutement@/i',
            '/casablanca finance city/i',
            '/^postuler$/i',
            '/^partagez cette annonce$/i',
            '/^publi[ée]e? le\b/i',
            '/^r[ée]sum[ée] du poste$/i',
            '/^d[ée]tails de l\'annonce$/i',
            '/^poste propos[ée]\s*:/i',
            '/^profil recherch[ée]\s+pour le poste\s*:/i',
            '/^crit[èe]res de l\'annonce\s+pour le poste\s*:/i',
            '/^m[ée]tier\s*:?$/i',
            '/^r[ée]gion\s*:/i',
            '/^ville\s*:/i',
            '/^niveau d\'exp[ée]rience\s*:/i',
            '/^niveau d\'[ée]tudes\s*:/i',
            '/^nom\s*"?$/i',
            '/^entreprise\b/i',
            '/^description de l\'entreprise\s*:/i',
            '/^secteur d\'activit[eé]\b/i',
            '/^site internet\b/i',
            '/^offres d\'emploi\b/i',
            '/^voir toutes nos annonces\b/i',
            '/^tableau de bord\b/i',
            '/^publier une offre\b/i',
            '/^explorer etudiants\b/i',
            '/^candidats\b/i',
            '/^elite matchendin\b/i',
        ];

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }

            $compact = preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
            if (mb_strlen($compact) < 3) {
                continue;
            }

            if (preg_match('/(?:\s-\s){4,}/u', $compact)) {
                continue;
            }

            if (preg_match('/^https?:\/\//i', $compact)) {
                continue;
            }

            if (preg_match('/[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}/u', $compact)) {
                continue;
            }

            if (preg_match('/^[\W_]*$/u', $compact)) {
                continue;
            }

            $upper = mb_strtoupper($compact, 'UTF-8');
            if ($compact === $upper && mb_strlen($compact) <= 35) {
                continue;
            }

            $isNoise = false;
            foreach ($noisePatterns as $pattern) {
                if (preg_match($pattern, $compact)) {
                    $isNoise = true;
                    break;
                }
            }
            if ($isNoise) {
                continue;
            }

            $key = Str::lower($compact);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $kept[] = $compact;
        }

        if (count($kept) === 0) {
            return null;
        }

        return Str::limit(implode("\n", $kept), 3900, '');
    }
}
