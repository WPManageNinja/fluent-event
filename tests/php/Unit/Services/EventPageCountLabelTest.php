<?php

namespace FluentEvent\Tests\Unit\Services;

use Brain\Monkey\Functions;
use FluentEvent\App\Services\EventPageService;
use FluentEvent\Tests\TestCase;

class EventPageCountLabelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('_n')->alias(function ($single, $plural, $number) {
            return $number === 1 ? $single : $plural;
        });
        Functions\when('number_format_i18n')->alias('strval');
    }

    /**
     * @dataProvider switches
     */
    public function test_the_count_label_follows_the_guest_count_and_spots_left_switches($guestCount, $spotsLeft, $expected)
    {
        $formatted = ['guest_count' => $guestCount, 'spots_left' => $spotsLeft, 'capacity' => 2];

        $this->assertSame($expected, EventPageService::countLabel($formatted, $spotsLeft ? $spotsLeft . ' spot left' : ''));
    }

    public function switches()
    {
        return [
            'both'       => [1, 1, '1 out of 2 going'],
            'count only' => [1, null, '1 going'],
            'spots only' => [null, 1, '1 spot left'],
            'neither'    => [null, null, ''],
        ];
    }
}
