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
        $this->assertTrue($erase['items_removed']);
        $this->assertTrue($erase['done']);

        $this->assertSame([$other->id], Guest::query()->whereIn('id', [$byEmail->id, $upcoming->id, $byAccount->id, $other->id])->pluck('id')->all());
    }

    public function test_an_email_handed_to_someone_else_does_not_reach_the_old_owners_rows()
    {
        $member = $this->createUser('subscriber');
        $oldEmail = 'old_' . get_userdata($member)->user_email;
        $memberRow = $this->createGuest($this->createEvent(), ['user_id' => $member, 'email' => $oldEmail]);

        $newOwner = $this->createUser('subscriber');
        wp_update_user(['ID' => $newOwner, 'user_email' => $oldEmail]);
        $ownRow = $this->createGuest($this->createEvent(), ['email' => $oldEmail]);

        $handler = new PrivacyHandler();

        $this->assertSame(['fluent-event-guest-' . $ownRow->id], array_column($handler->export($oldEmail, 1)['data'], 'item_id'));

        $handler->erase($oldEmail, 1);
        $this->assertNotNull(Guest::query()->find($memberRow->id), "The old owner's registration was erased.");
        $this->assertNull(Guest::query()->find($ownRow->id));
    }

    public function test_an_invalid_email_matches_no_guest_rows()
    {
        $this->createGuest($this->createEvent(), ['email' => null]);

        $this->assertSame([], (new PrivacyHandler())->export('not-an-email', 1)['data']);
        $this->assertFalse((new PrivacyHandler())->erase('', 1)['items_removed']);
    }
}
