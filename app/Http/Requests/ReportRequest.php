<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Utils\DateTimeHelper;

class ReportRequest extends BaseRequest
{
    public static function resolveRange($request)
    {
        $startDate = $request->getSafe('start_date', 'sanitize_text_field');
        $endDate   = $request->getSafe('end_date', 'sanitize_text_field');

        $end   = DateTimeHelper::isDate($endDate) ? $endDate : gmdate('Y-m-d');
        $start = DateTimeHelper::isDate($startDate) ? $startDate : gmdate('Y-m-d', strtotime($end . ' -6 days'));

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        // One bucket per day/month is built for the span, so an unbounded range is unbounded work.
        $earliest = gmdate('Y-m-d', strtotime($end . ' -5 years'));
        if ($start < $earliest) {
            $start = $earliest;
        }

        return [$start, $end];
    }
}
