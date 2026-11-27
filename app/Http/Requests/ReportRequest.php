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
        $earliest = sprintf('%04d', substr($end, 0, 4) - 5) . substr($end, 4);
        if (!DateTimeHelper::isDate($earliest)) {
            $earliest = substr($earliest, 0, 8) . '28';
        }
        if ($start < $earliest) {
            $start = $earliest;
        }

        return [$start, $end];
    }
}
