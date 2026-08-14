<?php

namespace FluentEvent\App\Http\Requests;

class ReportRequest extends BaseRequest
{
    public static function resolveRange($request)
    {
        $isDate = function ($value) {
            return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
        };

        $startDate = $request->getSafe('start_date', 'sanitize_text_field');
        $endDate   = $request->getSafe('end_date', 'sanitize_text_field');

        $end   = $isDate($endDate) ? $endDate : gmdate('Y-m-d');
        $start = $isDate($startDate) ? $startDate : gmdate('Y-m-d', strtotime($end . ' -29 days'));

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }
}
