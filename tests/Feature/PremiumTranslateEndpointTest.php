<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PremiumTranslateEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('GEMINI_API_KEY=test-key');
        putenv('GEMINI_TOTAL_ATTEMPTS=1');
    }

    protected function tearDown(): void
    {
        putenv('GEMINI_API_KEY');
        putenv('GEMINI_TOTAL_ATTEMPTS');
        parent::tearDown();
    }

    public function test_translate_prefers_gemini_when_available(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"text":"Bonjour le monde"}',
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/translate', [
            'text' => 'Hello world',
            'target_language' => 'fr',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('text', 'Bonjour le monde');
    }

    public function test_translate_uses_public_fallback_when_gemini_fails(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'message' => 'Service unavailable',
                ],
            ], 503),
            'https://translate.googleapis.com/*' => Http::response([
                [
                    ['Bonjour', 'Hello', null, null, 1],
                    [' tout le monde', ' world', null, null, 1],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/translate', [
            'text' => 'Hello world',
            'target_language' => 'fr',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('text', 'Bonjour tout le monde')
            ->assertJsonPath('provider', 'fallback');
    }

    public function test_translate_returns_original_text_when_all_providers_fail(): void
    {
        $enterprise = User::factory()->create([
            'role' => 'enterprise',
            'subscription_tier' => 'pro',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'message' => 'Service unavailable',
                ],
            ], 503),
            'https://translate.googleapis.com/*' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($enterprise)->postJson('/api/premium/translate', [
            'text' => 'Hello world',
            'target_language' => 'fr',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('text', 'Hello world');

        $this->assertIsString($response->json('warning'));
        $this->assertNotSame('', trim((string) $response->json('warning')));
    }
}
