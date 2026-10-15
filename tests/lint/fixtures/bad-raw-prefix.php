<?php
/**
 * Fixture for tests/lint/raw-sql-prefix.php. Never loaded.
 *
 * violations() holds five calls the lint MUST report. correct() holds look-alikes
 * it must NOT report; they only use fe_ticket_types, so a false positive is easy to spot.
 */

class RawSqlPrefixFixture
{
    public function violations($query)
    {
        $query->selectRaw("SUM(CASE WHEN fe_guests.status = 'going' THEN 1 ELSE 0 END) AS going");

        $query->whereRaw("fe_calendars.status = 'public'");

        $query->orderByRaw('fcom_posts.created_at DESC');

        $query->fromRaw('fe_meta AS m WHERE fe_meta.object_type = "event"');

        // Multi-line: the literal is not on the line that opens the call.
        $query->selectRaw(
            'COUNT(fe_event_details.id) AS total'
        );
    }

    public function correct($query)
    {
        global $wpdb;

        $query->selectRaw("COUNT({$wpdb->prefix}fe_ticket_types.id) AS total");

        $table = $wpdb->prefix . 'fe_ticket_types';
        $query->whereRaw("`{$table}`.`status` = 'active'");

        $query->whereRaw($wpdb->prefix . 'fe_ticket_types.price > 0');

        $query->orderByRaw('(end_at < ?) ASC', ['now']);

        $query->where('fe_ticket_types.status', 'active');
    }
}
