<?php

namespace App\Http\Controllers;

use App\Services\Reports\DashboardMetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $dashboards) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', $this->dashboards->dashboard($request->user()));
    }
}
