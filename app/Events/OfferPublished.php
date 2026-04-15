<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Offre as JobOffer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OfferPublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public JobOffer $offer)
    {
    }
}
