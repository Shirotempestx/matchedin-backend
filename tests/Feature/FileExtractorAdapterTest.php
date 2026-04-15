<?php

namespace Tests\Feature;

use App\Services\ImportPipeline\FileExtractorAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FileExtractorAdapterTest extends TestCase
{
    public function test_extract_raw_text_uses_configured_endpoint_and_parses_new_payload_shape(): void
    {
        config(['services.neural_extractor.endpoint' => 'https://neural-extarctor.netlify.app/api/v1/extract']);

        Http::fake([
            'https://neural-extarctor.netlify.app/api/v1/extract' => Http::response([
                'apiVersion' => 'v1',
                'totalFiles' => 1,
                'successful' => 1,
                'failed' => 0,
                'results' => [
                    [
                        'success' => true,
                        'document' => [
                            'fileName' => 'job.pdf',
                            'chunks' => [
                                ['type' => 'heading', 'text' => 'ANGULAR Developer (M/F)'],
                                ['type' => 'paragraph', 'text' => 'Build scalable Angular applications.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $adapter = new FileExtractorAdapter();
        $file = UploadedFile::fake()->createWithContent('job.pdf', 'dummy pdf bytes');

        $rawText = $adapter->extractRawText([$file]);

        $this->assertSame("ANGULAR Developer (M/F)\nBuild scalable Angular applications.", $rawText);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://neural-extarctor.netlify.app/api/v1/extract';
        });
    }

    public function test_extract_raw_text_supports_nested_document_shape_and_document_text_fallback(): void
    {
        config(['services.neural_extractor.endpoint' => 'https://neural-extarctor.netlify.app/api/v1/extract']);

        Http::fake([
            'https://neural-extarctor.netlify.app/api/v1/extract' => Http::response([
                'apiVersion' => 'v1',
                'totalFiles' => 2,
                'successful' => 2,
                'failed' => 0,
                'results' => [
                    [
                        'success' => true,
                        'data' => [
                            'document' => [
                                'chunks' => [
                                    ['type' => 'heading', 'text' => 'Mission'],
                                    ['type' => 'paragraph', 'text' => 'Lead frontend architecture.'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'success' => true,
                        'document' => [
                            'text' => 'Fallback plain document text',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $adapter = new FileExtractorAdapter();
        $file = UploadedFile::fake()->createWithContent('job.pdf', 'dummy pdf bytes');

        $rawText = $adapter->extractRawText([$file]);

        $this->assertSame("Mission\nLead frontend architecture.\nFallback plain document text", $rawText);
    }

    public function test_extract_raw_text_throws_when_all_results_fail(): void
    {
        config(['services.neural_extractor.endpoint' => 'https://neural-extarctor.netlify.app/api/v1/extract']);

        Http::fake([
            'https://neural-extarctor.netlify.app/api/v1/extract' => Http::response([
                'apiVersion' => 'v1',
                'totalFiles' => 1,
                'successful' => 0,
                'failed' => 1,
                'results' => [
                    [
                        'success' => false,
                        'fileName' => 'corrupt.pdf',
                        'error' => 'Failed to parse PDF: Invalid header',
                    ],
                ],
            ], 200),
        ]);

        $adapter = new FileExtractorAdapter();
        $file = UploadedFile::fake()->createWithContent('corrupt.pdf', 'bad bytes');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No extractable text found in provided file.');

        $adapter->extractRawText([$file]);
    }
}
