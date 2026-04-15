<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Proxy opportunity search to the existing Offre index filtering.
     */
    public function searchOffres(Request $request)
    {
        return app(OffreController::class)->index($request);
    }

    /**
     * Proxy talent search to the existing student index filtering.
     */
    public function searchTalents(Request $request)
    {
        return app(UserController::class)->indexStudents($request);
    }
}
