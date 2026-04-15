<?php

namespace App\Services\ImportPipeline;

interface AIClassificationEngine
{
    /**
     * @return array{payload: array<string, mixed>, warnings: array<int, string>, retry_count: int}
     */
    public function classifyOffer(string $rawText): array;

    /**
     * @return array{payload: array<string, mixed>, warnings: array<int, string>, retry_count: int}
     */
    public function classifyStudentProfile(string $rawText): array;
}
