<?php

use App\Models\OfferReservation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    OfferReservation::query()
        ->where('status', OfferReservation::STATUS_PENDING)
        ->where('expires_at', '<', now())
        ->update([
            'status' => OfferReservation::STATUS_EXPIRED,
            'updated_at' => now(),
        ]);
})->name('offer-reservations-expire')->hourly();
