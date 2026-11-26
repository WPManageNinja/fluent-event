<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Hooks\Handlers\PrivacyHandler;
use FluentEvent\App\Models\Guest;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Tools > Export / Erase Personal Data reach every guest row of the person: by email, or by their account.
 */
class PrivacyTest extends IntegrationTestCase
{
    public function test_export_and_erase_cover_every_guest_row_of_the_email_and_its_account()
    {
        $userId = $this->createUser('subscriber');
        $email = get_userdata($userId)->user_email;

        $first = $this->createEvent();
        $second = $this->createEvent();

        $byEmail = $this->createGuest($first, ['email' => $email]);
        $upcoming = $this->createGuest($second, ['email' => $email, 'status' => 'going']);
        $byAccount = $this->createGuest($this->createEvent(), ['user_id' => $userId, 'email' => 'old_' . $email]);
        $other = $this->createGuest($first);

        $handler = new PrivacyHandler();

        $export = $handler->export($email, 1);
        $this->assertTrue($export['done']);
        $this->assertEqualsCanonicalizing(
            ['fluent-event-guest-' . $byEmail->id, 'fluent-event-guest-' . $upcoming->id, 'fluent-event-guest-' . $byAccount->id],
            array_column($export['data'], 'item_id')
        );

        $erase = $handler->erase($email, 1);
        $this->assertSame(3, $erase['items_removed']);
        $this->assertTrue($erase['done']);

        $this->assertSame([$other->id], Guest::query()->whereIn('id', [$byEmail->id, $upcoming->id, $byAccount->id, $other->id])->pluck('id')->all());
    }

    public function test_an_invalid_email_matches_no_guest_rows()
    {
        $this->createGuest($this->createEvent(), ['email' => null]);

        $this->assertSame([], (new PrivacyHandler())->export('not-an-email', 1)['data']);
        $this->assertSame(0, (new PrivacyHandler())->erase('', 1)['items_removed']);
    }
}
