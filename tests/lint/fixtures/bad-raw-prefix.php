<?php
/**
 * Fixture for tests/lint/raw-sql-prefix.php. Never loaded.
 *
 * Every line marked must-fire has to be reported, and no other line:
 * RawSqlPrefixLintTest compares the lint's output with the markers.
 * correct() holds the look-alikes that must stay quiet.
 */

class RawSqlPrefixFixture
{
    public function violations($query, $status, $count)
    {
        $query->selectRaw("SUM(CASE WHEN fe_guests.status = 'going' THEN 1 ELSE 0 END) AS going"); // must-fire

        $query->whereRaw("fe_calendars.status = 'public'"); // must-fire

        $query->orderByRaw('fcom_posts.created_at DESC'); // must-fire

        $query->fromRaw('fe_meta AS m WHERE m.object_type = "event"'); // must-fire

        // The table sits on the line after the call opens; that is the line reported.
        $query->selectRaw(
            'COUNT(fe_event_details.id) AS total' // must-fire
        );

        $query->selectRaw('COUNT(wp_fe_guests.id) AS total'); // must-fire

        $query->selectRaw('COUNT(`fe_guests`.`id`) AS total'); // must-fire

        $query->whereRaw('EXISTS (SELECT 1 FROM fe_guests g WHERE g.event_id = 1)'); // must-fire

        $query->whereRaw("fe_guests.status = '$status'"); // must-fire

        $query->selectRaw($count . ' fe_guests.id'); // must-fire

        $query->selectRaw('COUNT(posts.ID) AS total'); // must-fire

        $sql = 'SUM(fe_guests.is_buyer)'; // must-fire
        $query->selectRaw($sql);

        $query->selectRaw(<<<SQL
            COUNT(fe_meta.id) -- must-fire
            SQL);
    }

    public function correct($query)
    {
        global $wpdb;

        $query->selectRaw("COUNT({$wpdb->prefix}fe_ticket_types.id) AS total");

        $table = $wpdb->prefix . 'fe_ticket_types';
        $query->whereRaw("`{$table}`.`status` = 'active'");

        $query->whereRaw($wpdb->prefix . 'fe_ticket_types.price > 0');

        $query->whereRaw("EXISTS (SELECT 1 FROM {$wpdb->prefix}fe_ticket_types t WHERE t.id = 1)");

        $query->selectRaw("COUNT({$wpdb->posts}.ID) AS total");

        $query->whereRaw("note = 'see fe_ticket_types.price'");

        $sql = "SUM({$wpdb->prefix}fe_ticket_types.price)";
        $query->selectRaw($sql);

        $query->selectRaw(<<<SQL
            COUNT({$wpdb->prefix}fe_ticket_types.id)
            SQL);

        $query->orderByRaw('(end_at < ?) ASC', ['now']);

        $query->selectRaw('COUNT(*) AS fe_total');

        $query->where('fe_ticket_types.status', 'active');
    }
}
