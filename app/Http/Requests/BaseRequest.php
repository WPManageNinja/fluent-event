<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Utils\Sanitizer;

class BaseRequest
{
    const SORTABLE = ['id'];
    const PER_PAGE = 20;
    const PER_PAGE_MAX = 100;

    public static function perPage($request)
    {
        $perPage = (int) $request->getSafe('per_page', Sanitizer::INTVAL, static::PER_PAGE);

        if ($perPage < 1) {
            $perPage = static::PER_PAGE;
        }

        return min($perPage, static::PER_PAGE_MAX);
    }

    public static function sorting($request)
    {
        $orderBy = $request->getSafe('order_by', Sanitizer::SANITIZE_TEXT_FIELD, 'id');
        $orderType = strtoupper($request->getSafe('order_type', Sanitizer::SANITIZE_TEXT_FIELD, 'ASC'));

        return [
            in_array($orderBy, static::SORTABLE, true) ? $orderBy : 'id',
            $orderType === 'DESC' ? 'DESC' : 'ASC',
        ];
    }
}
