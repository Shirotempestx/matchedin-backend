<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Offre;
use App\Services\VipAllocationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVipMatches implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public Offre $offer)
    {
    }

    public function handle(VipAllocationService $vipAllocationService): void
    {
        $vipAllocationService->allocateReservations($this->offer);
    }
}
