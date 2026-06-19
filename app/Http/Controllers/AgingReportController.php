<?php

namespace App\Http\Controllers;

use App\Services\AgingService;
use Inertia\Inertia;
use Inertia\Response;

class AgingReportController extends Controller
{
    public function index(AgingService $aging): Response
    {
        return Inertia::render('Reports/Aging', [
            'report' => $aging->report(),
        ]);
    }
}
