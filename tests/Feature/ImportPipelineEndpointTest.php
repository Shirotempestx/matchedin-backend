<?php

namespace Tests\Feature;

use App\Services\ImportPipeline\AIClassificationEngine;
use App\Services\ImportPipeline\FileExtractorAdapter;
use App\Services\ImportPipeline\UrlExtractorAdapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ImportPipelineEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_offer_import_requires_authentication(): void
    {
        $response = $this->postJson('/api/premium/import/offer', [
            'sourceType' => 'url',
            'url' => 'https://example.com/job',
        ]);

        $response->assertStatus(401);
    }

    public function test_offer_import_rejects_non_enterprise_role(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->postJson('/api/premium/import/offer', [
            'sourceType' => 'url',
            'url' => 'https://example.com/job',
        ]);

        $response->assertStatus(403);
    }

    public function test_offer_import_rejects_enterprise_without_subscription(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => null,
        ]);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/import/offer', [
            'sourceType' => 'url',
            'url' => 'https://example.com/job',
        ]);

        $response->assertStatus(403);
    }

    public function test_offer_import_uses_file_source_and_returns_allocation(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        $fileAdapter = Mockery::mock(FileExtractorAdapter::class);
        $urlAdapter = Mockery::mock(UrlExtractorAdapter::class);
        $classifier = Mockery::mock(AIClassificationEngine::class);

        $fileAdapter
            ->shouldReceive('extractRawText')
            ->once()
            ->andReturn('Senior backend engineer role with Laravel and SQL.');

        $urlAdapter
            ->shouldNotReceive('extractRawText');

        $classifier
            ->shouldReceive('classifyOffer')
            ->once()
            ->andReturn([
                'payload' => [
                    'title' => 'Senior Backend Engineer',
                    'company' => 'Acme',
                    'location' => 'Casablanca',
                    'employmentType' => 'Full-time',
                    'workMode' => 'Hybrid',
                    'skillsRequired' => ['Laravel', 'SQL'],
                    'description' => 'Build APIs and maintain backend services.',
                    'experienceLevel' => 'Senior',
                    'salaryMin' => 14000,
                    'salaryMax' => 22000,
                    'internshipPeriod' => null,
                    'niveauEtude' => 'Bac+5',
                    'placesDemanded' => 2,
                    'startDate' => '2026-05-01',
                    'endDate' => '2026-12-31',
                ],
                'warnings' => [],
                'retry_count' => 0,
            ]);

        $this->app->instance(FileExtractorAdapter::class, $fileAdapter);
        $this->app->instance(UrlExtractorAdapter::class, $urlAdapter);
        $this->app->instance(AIClassificationEngine::class, $classifier);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/import/offer', [
            'sourceType' => 'file',
            'files' => [UploadedFile::fake()->create('job.pdf', 50, 'application/pdf')],
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('entity', 'offer')
            ->assertJsonPath('sourceType', 'file')
            ->assertJsonPath('classification.payload.title', 'Senior Backend Engineer')
            ->assertJsonPath('allocation.offerFormDraft.contract_type', 'CDI')
            ->assertJsonPath('allocation.offerFormDraft.work_mode', 'Hybrid')
            ->assertJsonPath('allocation.offerFormDraft.salary_min', 14000)
            ->assertJsonPath('allocation.offerFormDraft.salary_max', 22000)
            ->assertJsonPath('allocation.offerFormDraft.niveau_etude', 'Bac+5')
            ->assertJsonPath('allocation.offerFormDraft.places_demanded', 2)
            ->assertJsonPath('allocation.offerFormDraft.start_date', '2026-05-01')
            ->assertJsonPath('allocation.offerFormDraft.end_date', '2026-12-31');
    }

    public function test_offer_import_uses_url_source_and_returns_allocation(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        $fileAdapter = Mockery::mock(FileExtractorAdapter::class);
        $urlAdapter = Mockery::mock(UrlExtractorAdapter::class);
        $classifier = Mockery::mock(AIClassificationEngine::class);

        $fileAdapter
            ->shouldNotReceive('extractRawText');

        $urlAdapter
            ->shouldReceive('extractRawText')
            ->once()
            ->with('https://example.com/job')
            ->andReturn('Internship role with React and TypeScript');

        $classifier
            ->shouldReceive('classifyOffer')
            ->once()
            ->andReturn([
                'payload' => [
                    'title' => 'Frontend Intern',
                    'company' => null,
                    'location' => 'Rabat',
                    'employmentType' => 'Internship',
                    'workMode' => 'Remote',
                    'skillsRequired' => ['React', 'TypeScript'],
                    'description' => 'Assist in frontend implementation.',
                    'experienceLevel' => null,
                    'salaryMin' => 3000,
                    'salaryMax' => 5000,
                    'internshipPeriod' => 6,
                    'niveauEtude' => 'Bac+3',
                    'placesDemanded' => 1,
                    'startDate' => '2026-06-01',
                    'endDate' => '2026-11-30',
                ],
                'warnings' => ['Unknown keys removed: foo'],
                'retry_count' => 1,
            ]);

        $this->app->instance(FileExtractorAdapter::class, $fileAdapter);
        $this->app->instance(UrlExtractorAdapter::class, $urlAdapter);
        $this->app->instance(AIClassificationEngine::class, $classifier);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/import/offer', [
            'sourceType' => 'url',
            'url' => 'https://example.com/job',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('sourceType', 'url')
            ->assertJsonPath('classification.retry_count', 1)
            ->assertJsonPath('allocation.offerFormDraft.contract_type', 'Stage')
            ->assertJsonPath('allocation.offerFormDraft.work_mode', 'Remote')
            ->assertJsonPath('allocation.offerFormDraft.salary_min', 3000)
            ->assertJsonPath('allocation.offerFormDraft.salary_max', 5000)
            ->assertJsonPath('allocation.offerFormDraft.internship_period', 6)
            ->assertJsonPath('allocation.offerFormDraft.niveau_etude', 'Bac+3')
            ->assertJsonPath('allocation.offerFormDraft.places_demanded', 1)
            ->assertJsonPath('allocation.offerFormDraft.start_date', '2026-06-01')
            ->assertJsonPath('allocation.offerFormDraft.end_date', '2026-11-30');
    }

    public function test_offer_import_uses_text_source_and_bypasses_extractors(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        $fileAdapter = Mockery::mock(FileExtractorAdapter::class);
        $urlAdapter = Mockery::mock(UrlExtractorAdapter::class);
        $classifier = Mockery::mock(AIClassificationEngine::class);

        $fileAdapter
            ->shouldNotReceive('extractRawText');

        $urlAdapter
            ->shouldNotReceive('extractRawText');

        $classifier
            ->shouldReceive('classifyOffer')
            ->once()
            ->with('Je cherche un developpeur full stack avec React et Laravel.')
            ->andReturn([
                'payload' => [
                    'title' => 'Developpeur Full Stack',
                    'company' => null,
                    'location' => 'Casablanca',
                    'employmentType' => 'Full-time',
                    'workMode' => 'Hybrid',
                    'skillsRequired' => ['React', 'Laravel'],
                    'description' => 'Concevoir et maintenir des applications web.',
                    'experienceLevel' => 'Mid',
                    'salaryMin' => 12000,
                    'salaryMax' => 18000,
                    'internshipPeriod' => null,
                    'niveauEtude' => 'Bac+3',
                    'placesDemanded' => 1,
                    'startDate' => '2026-05-01',
                    'endDate' => '2026-12-31',
                ],
                'warnings' => [],
                'retry_count' => 0,
            ]);

        $this->app->instance(FileExtractorAdapter::class, $fileAdapter);
        $this->app->instance(UrlExtractorAdapter::class, $urlAdapter);
        $this->app->instance(AIClassificationEngine::class, $classifier);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/import/offer', [
            'sourceType' => 'text',
            'text' => 'Je cherche un developpeur full stack avec React et Laravel.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('sourceType', 'text')
            ->assertJsonPath('allocation.offerFormDraft.title', 'Developpeur Full Stack')
            ->assertJsonPath('allocation.offerFormDraft.contract_type', 'CDI')
            ->assertJsonPath('allocation.offerFormDraft.work_mode', 'Hybrid');
    }

    public function test_offer_import_text_source_requires_text_payload(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/import/offer', [
            'sourceType' => 'text',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    }

    public function test_offer_import_normalizes_textual_dates_into_iso_format(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        $fileAdapter = Mockery::mock(FileExtractorAdapter::class);
        $urlAdapter = Mockery::mock(UrlExtractorAdapter::class);
        $classifier = Mockery::mock(AIClassificationEngine::class);

        $urlAdapter
            ->shouldReceive('extractRawText')
            ->once()
            ->with('https://example.com/job')
            ->andReturn('Offer with textual dates.');

        $classifier
            ->shouldReceive('classifyOffer')
            ->once()
            ->andReturn([
                'payload' => [
                    'title' => 'Full-Stack Developer',
                    'company' => 'TechNova',
                    'location' => 'Casablanca',
                    'employmentType' => 'Full-time',
                    'workMode' => 'Hybrid',
                    'skillsRequired' => ['Laravel', 'React'],
                    'description' => 'Build modern web platforms.',
                    'experienceLevel' => 'Mid',
                    'salaryMin' => 12000,
                    'salaryMax' => 18000,
                    'internshipPeriod' => null,
                    'niveauEtude' => 'Bac+3',
                    'placesDemanded' => 1,
                    'startDate' => 'May 4, 2026',
                    'endDate' => '30 September 2026',
                ],
                'warnings' => [],
                'retry_count' => 0,
            ]);

        $this->app->instance(FileExtractorAdapter::class, $fileAdapter);
        $this->app->instance(UrlExtractorAdapter::class, $urlAdapter);
        $this->app->instance(AIClassificationEngine::class, $classifier);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/import/offer', [
            'sourceType' => 'url',
            'url' => 'https://example.com/job',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('allocation.offerFormDraft.start_date', '2026-05-04')
            ->assertJsonPath('allocation.offerFormDraft.end_date', '2026-09-30');
    }

    public function test_offer_import_fallback_cleans_offer_letter_description_noise(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        $fileAdapter = Mockery::mock(FileExtractorAdapter::class);
        $urlAdapter = Mockery::mock(UrlExtractorAdapter::class);
        $classifier = Mockery::mock(AIClassificationEngine::class);

        $rawText = "Casablanca, April 13, 2026\nCasablanca, Morocco\nOffer of Employment: Full-Stack Developer\nDear Yahya,\n"
            . "In this role, you will develop robust web applications using Laravel, React, and Node.js.\n"
            . "Key Terms of the Employment Offer:\n"
            . "Compensation: Competitive monthly salary.\n"
            . "Benefits: Comprehensive health insurance (CNOPS/CNSS).\n"
            . "recrutement@technova.ma";

        $urlAdapter
            ->shouldReceive('extractRawText')
            ->once()
            ->with('https://example.com/job')
            ->andReturn($rawText);

        $classifier
            ->shouldReceive('classifyOffer')
            ->once()
            ->andThrow(new RuntimeException('AI classification failed after retry.'));

        $this->app->instance(FileExtractorAdapter::class, $fileAdapter);
        $this->app->instance(UrlExtractorAdapter::class, $urlAdapter);
        $this->app->instance(AIClassificationEngine::class, $classifier);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/import/offer', [
            'sourceType' => 'url',
            'url' => 'https://example.com/job',
        ]);

        $response->assertStatus(200);

        $title = (string) data_get($response->json(), 'allocation.offerFormDraft.title', '');
        $location = (string) data_get($response->json(), 'allocation.offerFormDraft.location', '');
        $description = (string) data_get($response->json(), 'allocation.offerFormDraft.description', '');

        $this->assertSame('Full-Stack Developer', $title);
        $this->assertSame('Casablanca, Morocco', $location);
        $this->assertNotSame('', trim($description));
        $this->assertStringContainsString('In this role', $description);
        $this->assertStringNotContainsString('Dear Yahya', $description);
        $this->assertStringNotContainsString('Offer of Employment', $description);
        $this->assertStringNotContainsString('recrutement@', $description);
    }

    public function test_offer_import_returns_success_with_fallback_when_ai_fails(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        $fileAdapter = Mockery::mock(FileExtractorAdapter::class);
        $urlAdapter = Mockery::mock(UrlExtractorAdapter::class);
        $classifier = Mockery::mock(AIClassificationEngine::class);

        $urlAdapter
            ->shouldReceive('extractRawText')
            ->once()
            ->andReturn('Some extracted raw text');

        $classifier
            ->shouldReceive('classifyOffer')
            ->once()
            ->andThrow(new RuntimeException('AI classification failed after retry.'));

        $this->app->instance(FileExtractorAdapter::class, $fileAdapter);
        $this->app->instance(UrlExtractorAdapter::class, $urlAdapter);
        $this->app->instance(AIClassificationEngine::class, $classifier);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/import/offer', [
            'sourceType' => 'url',
            'url' => 'https://example.com/job',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('entity', 'offer')
            ->assertJsonPath('classification.retry_count', 0)
            ->assertJsonPath('classification.warnings.0', 'AI classification fallback used: AI classification failed after retry.');
    }

    public function test_student_profile_import_rejects_non_student_role(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/import/student-profile', [
            'sourceType' => 'url',
            'url' => 'https://example.com/profile',
        ]);

        $response->assertStatus(403);
    }

    public function test_student_profile_import_supports_file_source(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $fileAdapter = Mockery::mock(FileExtractorAdapter::class);
        $urlAdapter = Mockery::mock(UrlExtractorAdapter::class);
        $classifier = Mockery::mock(AIClassificationEngine::class);

        $fileAdapter
            ->shouldReceive('extractRawText')
            ->once()
            ->andReturn('Profile text with React and Laravel skills.');

        $classifier
            ->shouldReceive('classifyStudentProfile')
            ->once()
            ->andReturn([
                'payload' => [
                    'headline' => 'Junior Fullstack Developer',
                    'city' => 'Marrakech',
                    'availability' => 'Available immediately',
                    'workMode' => 'Hybrid',
                    'bio' => 'Motivated student profile summary.',
                    'githubUrl' => 'https://github.com/junior',
                    'linkedinUrl' => null,
                    'portfolioUrl' => null,
                    'cvUrl' => null,
                    'profileType' => 'IT',
                    'preferredLanguage' => 'en',
                    'skills' => ['React', 'Laravel'],
                ],
                'warnings' => [],
                'retry_count' => 0,
            ]);

        $this->app->instance(FileExtractorAdapter::class, $fileAdapter);
        $this->app->instance(UrlExtractorAdapter::class, $urlAdapter);
        $this->app->instance(AIClassificationEngine::class, $classifier);

        $response = $this->actingAs($student)->postJson('/api/premium/import/student-profile', [
            'sourceType' => 'file',
            'files' => [UploadedFile::fake()->create('profile.pdf', 40, 'application/pdf')],
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('entity', 'student-profile')
            ->assertJsonPath('allocation.studentProfileDraft.headline', 'Junior Fullstack Developer')
            ->assertJsonPath('allocation.studentProfileDraft.profile_type', 'IT');
    }

    public function test_student_profile_import_supports_url_source(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $fileAdapter = Mockery::mock(FileExtractorAdapter::class);
        $urlAdapter = Mockery::mock(UrlExtractorAdapter::class);
        $classifier = Mockery::mock(AIClassificationEngine::class);

        $urlAdapter
            ->shouldReceive('extractRawText')
            ->once()
            ->with('https://example.com/profile')
            ->andReturn('Profile text from URL source.');

        $classifier
            ->shouldReceive('classifyStudentProfile')
            ->once()
            ->andReturn([
                'payload' => [
                    'headline' => 'Data Analyst Student',
                    'city' => 'Fes',
                    'availability' => null,
                    'workMode' => 'Remote',
                    'bio' => 'Focused on data workflows.',
                    'githubUrl' => null,
                    'linkedinUrl' => 'https://linkedin.com/in/test',
                    'portfolioUrl' => null,
                    'cvUrl' => null,
                    'profileType' => 'NON_IT',
                    'preferredLanguage' => 'fr',
                    'skills' => ['Excel', 'Power BI'],
                ],
                'warnings' => ['Unknown keys removed: x'],
                'retry_count' => 1,
            ]);

        $this->app->instance(FileExtractorAdapter::class, $fileAdapter);
        $this->app->instance(UrlExtractorAdapter::class, $urlAdapter);
        $this->app->instance(AIClassificationEngine::class, $classifier);

        $response = $this->actingAs($student)->postJson('/api/premium/import/student-profile', [
            'sourceType' => 'url',
            'url' => 'https://example.com/profile',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('sourceType', 'url')
            ->assertJsonPath('classification.retry_count', 1)
            ->assertJsonPath('allocation.studentProfileDraft.preferred_language', 'fr');
    }

    public function test_student_profile_import_returns_success_with_fallback_when_ai_fails(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $fileAdapter = Mockery::mock(FileExtractorAdapter::class);
        $urlAdapter = Mockery::mock(UrlExtractorAdapter::class);
        $classifier = Mockery::mock(AIClassificationEngine::class);

        $urlAdapter
            ->shouldReceive('extractRawText')
            ->once()
            ->andReturn('Some profile raw text');

        $classifier
            ->shouldReceive('classifyStudentProfile')
            ->once()
            ->andThrow(new RuntimeException('AI classification failed after retry.'));

        $this->app->instance(FileExtractorAdapter::class, $fileAdapter);
        $this->app->instance(UrlExtractorAdapter::class, $urlAdapter);
        $this->app->instance(AIClassificationEngine::class, $classifier);

        $response = $this->actingAs($student)->postJson('/api/premium/import/student-profile', [
            'sourceType' => 'url',
            'url' => 'https://example.com/profile',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('entity', 'student-profile')
            ->assertJsonPath('classification.retry_count', 0)
            ->assertJsonPath('classification.warnings.0', 'AI classification fallback used: AI classification failed after retry.');
    }

    public function test_legacy_extract_endpoint_delegates_to_file_adapter(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        $fileAdapter = Mockery::mock(FileExtractorAdapter::class);
        $fileAdapter
            ->shouldReceive('extractRawText')
            ->once()
            ->andReturn("Senior Data Engineer\nBuild ETL and data pipelines with SQL and Python.");

        $this->app->instance(FileExtractorAdapter::class, $fileAdapter);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/extract', [
            'files' => [UploadedFile::fake()->create('job.pdf', 50, 'application/pdf')],
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('source', 'document')
            ->assertJsonPath('extractor', null)
            ->assertJsonPath('parsed_offer.title', 'Senior Data Engineer');
    }

    public function test_legacy_extract_url_endpoint_delegates_to_url_adapter(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        $urlAdapter = Mockery::mock(UrlExtractorAdapter::class);
        $urlAdapter
            ->shouldReceive('extractRawText')
            ->once()
            ->with('https://example.com/job')
            ->andReturn("Frontend Developer\nBuild interfaces with React and TypeScript.");

        $this->app->instance(UrlExtractorAdapter::class, $urlAdapter);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/extract-url', [
            'url' => 'https://example.com/job',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('source', 'url')
            ->assertJsonPath('url', 'https://example.com/job')
            ->assertJsonPath('extractor', null)
            ->assertJsonPath('parsed_offer.title', 'Frontend Developer');
    }
}
