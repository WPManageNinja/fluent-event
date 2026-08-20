<?php

namespace FluentEvent\App\Utils;

use DateTime;
use DateTimeZone;

class DateTimeHelper
{
    const FORMAT = 'Y-m-d H:i:s';

    public static function getValidatedTimeZone($timezone)
    {
        try {
            return (new DateTimeZone((string) $timezone))->getName();
        } catch (\Exception $e) {
            return wp_timezone()->getName();
        }
    }

    public static function convertToUtc($dateTime, $timezone, $format = self::FORMAT)
    {
        $timezone = self::getValidatedTimeZone($timezone);

        $dateTime = new DateTime($dateTime, new DateTimeZone($timezone));
        $dateTime->setTimezone(new DateTimeZone('UTC'));

        return $dateTime->format($format);
    }

    public static function convertFromUtc($dateTime, $timezone, $format = self::FORMAT)
    {
        $timezone = self::getValidatedTimeZone($timezone);

        $dateTime = new DateTime($dateTime, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone($timezone));

        return $dateTime->format($format);
    }
}
