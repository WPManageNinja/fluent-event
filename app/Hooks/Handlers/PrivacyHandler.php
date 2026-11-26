<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\Models\Guest;

// Tools > Export / Erase Personal Data: every guest row matching the email, or the account that owns it.
class PrivacyHandler
{
    const PER_PAGE = 100;

    public function register()
    {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'addExporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'addEraser']);
    }

    public function addExporter($exporters)
    {
        $exporters['fluent-event'] = [
            'exporter_friendly_name' => __('FluentEvent Guests', 'fluent-event'),
            'callback'               => [$this, 'export'],
        ];

        return $exporters;
    }

    public function addEraser($erasers)
    {
        $erasers['fluent-event'] = [
            'eraser_friendly_name' => __('FluentEvent Guests', 'fluent-event'),
            'callback'             => [$this, 'erase'],
        ];

        return $erasers;
    }

    public function export($email, $page = 1)
    {
        $guests = $this->guestsQuery($email)
            ->with('event.post')
            ->orderBy('id')
            ->offset((max(1, (int) $page) - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE)
            ->get();

        $items = [];

        foreach ($guests as $guest) {
            $fields = [
                'event'         => [__('Event', 'fluent-event'), $guest->event ? $guest->event->title : ''],
                'name'          => [__('Name', 'fluent-event'), $guest->name],
                'email'         => [__('Email', 'fluent-event'), $guest->email],
                'status'        => [__('Status', 'fluent-event'), $guest->status],
                'source'        => [__('Source', 'fluent-event'), $guest->source],
                'checked_in_at' => [__('Checked In At', 'fluent-event'), $guest->checked_in_at],
                'created_at'    => [__('Registered At', 'fluent-event'), (string) $guest->created_at],
                'meta'          => [__('Additional Data', 'fluent-event'), $guest->meta ? wp_json_encode($guest->meta) : ''],
            ];

            $data = [];
            foreach ($fields as [$label, $value]) {
                if ($value !== null && $value !== '') {
                    $data[] = ['name' => $label, 'value' => (string) $value];
                }
            }

            $items[] = [
                'group_id'          => 'fluent-event-guests',
                'group_label'       => __('Event Registrations', 'fluent-event'),
                'group_description' => __('Events you registered for or answered.', 'fluent-event'),
                'item_id'           => 'fluent-event-guest-' . $guest->id,
                'data'              => $data,
            ];
        }

        return [
            'data' => $items,
            'done' => count($guests) < self::PER_PAGE,
        ];
    }

    // Always the first page: each call deletes what it read, so the next call sees the rest.
    public function erase($email, $page = 1)
    {
        $guests = $this->guestsQuery($email)->orderBy('id')->limit(self::PER_PAGE)->get();

        // No controller is on the stack, so this path fires the per-guest delete hooks itself.
        $removed = 0;
        foreach ($guests as $guest) {
            do_action('fluent_event/guest/before_deleted', $guest);
            if ($guest->delete()) {
                $removed++;
                do_action('fluent_event/guest/deleted', $guest);
            }
        }

        $retained = count($guests) - $removed;

        return [
            'items_removed'  => $removed,
            'items_retained' => $retained > 0,
            'messages'       => $retained ? [__('Some event registrations could not be deleted. Please try again.', 'fluent-event')] : [],
            // A failed delete would come back on the next call forever.
            'done'           => $retained > 0 || count($guests) < self::PER_PAGE,
        ];
    }

    private function guestsQuery($email)
    {
        $email = sanitize_email($email);
        $user = $email ? get_user_by('email', $email) : false;

        // An invalid email must match nothing, not the rows whose email is blank.
        if (!$email) {
            return Guest::query()->whereRaw('1 = 0');
        }

        return Guest::query()->where(function ($query) use ($email, $user) {
            $query->where('email', $email);

            if ($user) {
                $query->orWhere('user_id', $user->ID);
            }
        });
    }
}
