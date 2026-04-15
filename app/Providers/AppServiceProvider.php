<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\OfferPublished;
use App\Jobs\ProcessVipMatches;
use App\Services\ImportPipeline\AIClassificationEngine;
use App\Services\ImportPipeline\GeminiClassificationEngine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AIClassificationEngine::class, GeminiClassificationEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(OfferPublished::class, function (OfferPublished $event): void {
            ProcessVipMatches::dispatch($event->offer);
        });
    }
}
