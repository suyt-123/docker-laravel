<?php

namespace App\Http\Controllers;

use App\Services\Reports\WorkHoursReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkHoursReportController extends Controller
{
    public function __construct(private readonly WorkHoursReportService $reports) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Reports/WorkHours', [
            ...$this->reports->report(
                $request->user(),
                $request->query('period'),
                $request->query('date'),
            ),
        ]);
    }
}
