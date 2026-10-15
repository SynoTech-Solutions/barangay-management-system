<?php

namespace App\Http\Controllers\Reports;

use App\Enums\BusinessDimension;
use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Businesses\BusinessReportRequest;
use App\Http\Support\EnumOptions;
use App\Queries\BusinessDimensionReport;
use Inertia\Inertia;
use Inertia\Response;

class BusinessReportController extends Controller
{
    public function __invoke(BusinessReportRequest $request, BusinessDimensionReport $report): Response
    {
        $dimension = $request->dimension();
        $status = $request->status();

        return Inertia::render('reports/Businesses', [
            'rows' => $report->counts($dimension, $status),
            'filters' => ['dimension' => $dimension->value, 'status' => $status?->value],
            'dimensions' => EnumOptions::of(BusinessDimension::cases()),
            'statuses' => EnumOptions::of(BusinessStatus::cases()),
        ]);
    }
}
