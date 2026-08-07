<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\Utils\CustomSanitizer;

class BaseRequest
{
    const SORTABLE = ['id'];
    const SANITIZERS = [];
    const PER_PAGE = 20;
    const PER_PAGE_MAX = 100;
    const ORDER_BY = 'id';
    const ORDER_TYPE = 'ASC';

    public static function sanitize($data)
    {
        return CustomSanitizer::sanitize($data, static::SANITIZERS);
    }

    public static function perPage($request)
    {
        $perPage = (int) $request->getSafe('per_page', 'intval', static::PER_PAGE);

        if ($perPage < 1) {
            $perPage = static::PER_PAGE;
        }

        return min($perPage, static::PER_PAGE_MAX);
    }

    public static function sorting($request)
    {
        $orderBy = $request->getSafe('order_by', 'sanitize_text_field', static::ORDER_BY);
        $orderType = strtoupper($request->getSafe('order_type', 'sanitize_text_field', static::ORDER_TYPE));

        return [
            in_array($orderBy, static::SORTABLE, true) ? $orderBy : static::ORDER_BY,
            in_array($orderType, ['ASC', 'DESC'], true) ? $orderType : static::ORDER_TYPE,
        ];
    }
}
