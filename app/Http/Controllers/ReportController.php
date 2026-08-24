<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Services\ReportService;
use FluentEvent\App\Http\Requests\ReportRequest;
use FluentEvent\Framework\Http\Request\Request;

class ReportController extends Controller
{
    public function getReports()
    {
        $data = ReportService::getStats();

        $data['ongoing_events'] = ReportService::getOngoingEvents();
        $data['upcoming_events'] = ReportService::getUpcomingEvents();
        $data['top_events'] = ReportService::getTopEvents();

        return $this->sendSuccess($data);
    }

    public function getGuestTrends(Request $request)
    {
        [$start, $end] = ReportRequest::resolveRange($request);

        return $this->sendSuccess(ReportService::getGuestTrends($start, $end));
    }
}
