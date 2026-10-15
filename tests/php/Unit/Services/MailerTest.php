<?php

namespace FluentEvent\Tests\Unit\Services;

use FluentEvent\App\Services\Mailer;
use FluentEvent\Tests\TestCase;

/**
 * From/Reply-To are built from admin-entered settings, so a CR/LF in them is a
 * header injection (rules/email.md). formatAddress() is the only path in.
 */
class MailerTest extends TestCase
{
    private function address($name, $email)
    {
        return $this->callProtected(Mailer::class, 'formatAddress', [$name, $email]);
    }

    public function test_a_name_and_email_form_a_named_address()
    {
        $this->assertSame('Events Team <events@example.com>', $this->address('Events Team', 'events@example.com'));
    }

    public function test_an_email_without_a_name_is_used_bare()
    {
        $this->assertSame('events@example.com', $this->address('', 'events@example.com'));
    }

    public function test_no_email_means_no_header()
    {
        $this->assertSame('', $this->address('Events Team', ''));
    }

    public function test_a_line_break_in_the_name_cannot_start_a_new_header()
    {
        $address = $this->address("Events\r\nBcc: spy@evil.test", 'events@example.com');

        $this->assertDoesNotMatchRegularExpression('/[\r\n]/', $address);
        $this->assertStringEndsWith('<events@example.com>', $address);
    }

    public function test_a_line_break_in_the_email_cannot_start_a_new_header()
    {
        $address = $this->address('Events', "events@example.com\r\nBcc: spy@evil.test");

        $this->assertDoesNotMatchRegularExpression('/[\r\n]/', $address);
    }

    public function test_line_breaks_are_stripped_from_the_subject()
    {
        $this->assertSame('Reminder: Launch', $this->callProtected(Mailer::class, 'stripLineBreaks', ["Reminder:\r\n Launch"]));
    }
}
