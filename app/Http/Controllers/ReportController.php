<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Services\ReportService;
use FluentEvent\App\Services\PermissionManager;
use FluentEvent\App\Http\Requests\ReportRequest;
use FluentEvent\Framework\Http\Request\Request;

class ReportController extends Controller
{
    public function getReports()
    {
        $data = ReportService::getStats();

        $canReadEvents = PermissionManager::userCan(['read_events', 'write_events']);
        $data['ongoing_events'] = $canReadEvents ? ReportService::getOngoingEvents() : [];
        $data['upcoming_events'] = $canReadEvents ? ReportService::getUpcomingEvents() : [];
        $data['top_events'] = $canReadEvents ? ReportService::getTopEvents() : [];

        return $data;
    }

    public function getGuestTrends(Request $request)
    {
        [$start, $end] = ReportRequest::resolveRange($request);

        return ReportService::getGuestTrends($start, $end);
    }
}
