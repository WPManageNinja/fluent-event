<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\App;

class Mailer
{
    public static function send($to, $subject, $body)
    {
        $settings = Settings::get(Settings::EMAIL);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if ($from = self::formatAddress($settings['from_name'], $settings['from_email'])) {
            $headers[] = 'From: ' . $from;
        }

        if ($replyTo = self::formatAddress($settings['reply_to_name'], $settings['reply_to_email'])) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $body = (string) App::make('view')->make('emails.template', [
            'email_body'   => $body,
            'email_footer' => $settings['email_footer'],
        ]);

        return wp_mail($to, self::stripLineBreaks($subject), $body, $headers);
    }

    protected static function formatAddress($name, $email)
    {
        $email = sanitize_email(self::stripLineBreaks($email));

        if (!$email) return '';

        $name = self::stripLineBreaks(sanitize_text_field($name));

        return $name ? sprintf('%s <%s>', $name, $email) : $email;
    }

    protected static function stripLineBreaks($value)
    {
        return str_replace(["\r", "\n"], '', (string) $value);
    }
}
