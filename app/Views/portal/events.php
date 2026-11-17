<?php
/**
 * [fluent_events]: every public calendar's events, or one calendar's, as cards, a timeline or a
 * month, with a chip per calendar to switch. Every control is a plain link, so each state has its own
 * URL and works without JavaScript; portal.js swaps the list in place when a chip is picked.
 *
 * @var array  $chips          title, slug ('' for All), color (sanitized hex or ''), active. Empty with one calendar.
 * @var bool   $show_calendar  Name each event's calendar: there are chips to switch between them.
 * @var string $url            The page showing the list, without query args.
 * @var string $view           cards|timeline|calendar
 * @var bool   $view_locked
 * @var string $filter         upcoming|all|past
 * @var string $search
 * @var array  $state          Query args every link carries.
 * @var array  $keep_empty     State keys a link keeps even when empty.
 * @var string $fluentEventPageVar       The pagination query arg.
 * @var string $fluentEventCalendarVar   The calendar query arg.
 * @var string $fluentEventSearchVar     The search query arg.
 * @var array  $rows           List views: one eventRow() per event.
 * @var int    $current_page
 * @var int    $last_page
 * @var array  $grid           Calendar view: FrontEndHandler::calendarGrid().
 */
defined('ABSPATH') || exit;

use FluentEvent\App\Services\Icon;

// A calendar's colour, inherited as currentColor by its dot or an empty cover.
$fluentEventTint = function ( $color ) {
    return $color ? ' style="color: ' . esc_attr( $color ) . '"' : '';
};

// add_query_arg() leaves values raw, so encode them; empty values drop out of the link unless kept.
$fluentEventLink = function ( array $args ) use ( $state, $url, $keep_empty ) {
    $args = array_filter( array_merge( $state, $args ), function ( $value, $key ) use ( $keep_empty ) {
        return strlen( $value ) || in_array( $key, $keep_empty, true );
    }, ARRAY_FILTER_USE_BOTH );

    return add_query_arg( array_map( 'rawurlencode', $args ), $url );
};

$fluentEventSpans = [
    'month' => __( 'Month', 'fluent-event' ),
    'week'  => __( 'Week', 'fluent-event' ),
    'day'   => __( 'Day', 'fluent-event' ),
];

// A calendar entry: its start time, or "Day N" on the later days of a multi-day event; the colour is its calendar's.
$fluentEventEntry = function ( array $entry, $class, $style = '' ) {
    $style .= $entry['color'] ? '--fe-event-color: ' . $entry['color'] . ';' : '';
    ?>
    <a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $entry['url'] ); ?>" title="<?php echo esc_attr( $entry['title'] ); ?>"<?php echo $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
        <span class="<?php echo esc_attr( $class ); ?>-time">
            <?php
            /* translators: %d: the day number within a multi-day event */
            echo esc_html( $entry['day_index'] > 1 ? sprintf( __( 'Day %d', 'fluent-event' ), $entry['day_index'] ) : $entry['time'] );
            ?>
        </span>
        <span class="<?php echo esc_attr( $class ); ?>-title"><?php echo esc_html( $entry['title'] ); ?></span>
    </a>
    <?php
};

$fluentEventFilters = [
    'all'      => __( 'All', 'fluent-event' ),
    'upcoming' => __( 'Upcoming', 'fluent-event' ),
    'past'     => __( 'Past', 'fluent-event' ),
];

$fluentEventViews = [
    'cards'    => [ __( 'Cards', 'fluent-event' ), Icon::GRID ],
    'timeline' => [ __( 'Timeline', 'fluent-event' ), Icon::TIMELINE ],
    'calendar' => [ __( 'Calendar', 'fluent-event' ), Icon::CALENDAR ],
];

$fluentEventEmpty = [
    'all'      => __( 'No events yet.', 'fluent-event' ),
    'upcoming' => __( 'No upcoming events.', 'fluent-event' ),
    'past'     => __( 'No past events.', 'fluent-event' ),
];
?>
<div class="fe-events-page" data-fe-events>
    <?php if ( $chips ) : ?>
        <?php // Every chip in one row; on wide screens portal.js moves those that don't fit into the "More" panel. ?>
        <div class="fe-calendar-chips" data-fe-chips>
            <nav class="fe-calendar-chips__row" aria-label="<?php esc_attr_e( 'Calendars', 'fluent-event' ); ?>" data-fe-chips-row>
                <?php foreach ( $chips as $fluentEventChip ) : ?>
                    <a
                        class="fe-calendar-chips__chip<?php echo $fluentEventChip['active'] ? ' fe-calendar-chips__chip--active' : ''; ?>"
                        href="<?php echo esc_url( $fluentEventLink( [ $fluentEventCalendarVar => $fluentEventChip['slug'] ] ) ); ?>"
                        <?php echo $fluentEventChip['active'] ? 'aria-current="page"' : ''; ?>
                        data-fe-chip
                    >
                        <?php if ( $fluentEventChip['color'] ) : ?>
                            <span class="fe-calendar-chips__dot"<?php echo $fluentEventTint( $fluentEventChip['color'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure ?> aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="fe-calendar-chips__label" title="<?php echo esc_attr( $fluentEventChip['title'] ); ?>"><?php echo esc_html( $fluentEventChip['title'] ); ?></span>
                    </a>
                <?php endforeach; ?>

                <button
                    type="button"
                    class="fe-calendar-chips__chip fe-calendar-chips__more"
                    popovertarget="fe-calendar-more"
                    data-label="<?php /* translators: %d: number of calendars that didn't fit in the row */ esc_attr_e( 'More (%d)', 'fluent-event' ); ?>"
                    hidden
                    data-fe-chips-more
                ></button>
            </nav>

            <div id="fe-calendar-more" class="fe-calendar-chips__panel" popover aria-label="<?php esc_attr_e( 'More calendars', 'fluent-event' ); ?>" data-fe-chips-panel></div>
        </div>
    <?php endif; ?>

    <div class="fe-events-page__toolbar">
        <form class="fe-events-page__search" method="get" action="<?php echo esc_url( $url ); ?>" role="search">
            <input
                type="search"
                name="<?php echo esc_attr( $fluentEventSearchVar ); ?>"
                class="fe-events-page__search-input"
                value="<?php echo esc_attr( $search ); ?>"
                placeholder="<?php esc_attr_e( 'Search events', 'fluent-event' ); ?>"
                aria-label="<?php esc_attr_e( 'Search events', 'fluent-event' ); ?>"
            >
            <?php foreach ( array_diff_key( $state, [ $fluentEventSearchVar => '' ] ) as $fluentEventKey => $fluentEventValue ) : ?>
                <input type="hidden" name="<?php echo esc_attr( $fluentEventKey ); ?>" value="<?php echo esc_attr( $fluentEventValue ); ?>">
            <?php endforeach; ?>
        </form>

        <?php if ( 'calendar' !== $view ) : ?>
            <nav class="fe-switch" aria-label="<?php esc_attr_e( 'Show events', 'fluent-event' ); ?>">
                <?php foreach ( $fluentEventFilters as $fluentEventKey => $fluentEventLabel ) : ?>
                    <a
                        class="fe-switch__option<?php echo $fluentEventKey === $filter ? ' fe-switch__option--active' : ''; ?>"
                        href="<?php echo esc_url( $fluentEventLink( [ 'filter' => $fluentEventKey ] ) ); ?>"
                        <?php echo $fluentEventKey === $filter ? 'aria-current="true"' : ''; ?>
                    ><?php echo esc_html( $fluentEventLabel ); ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php if ( ! $view_locked ) : ?>
            <nav class="fe-switch" aria-label="<?php esc_attr_e( 'Switch between card, timeline and calendar view', 'fluent-event' ); ?>">
                <?php foreach ( $fluentEventViews as $fluentEventKey => list( $fluentEventLabel, $fluentEventIcon ) ) : ?>
                    <a
                        class="fe-switch__option fe-switch__option--icon<?php echo $fluentEventKey === $view ? ' fe-switch__option--active' : ''; ?>"
                        href="<?php echo esc_url( $fluentEventLink( [ 'view' => $fluentEventKey ] ) ); ?>"
                        title="<?php echo esc_attr( $fluentEventLabel ); ?>"
                        aria-label="<?php echo esc_attr( $fluentEventLabel ); ?>"
                        <?php echo $fluentEventKey === $view ? 'aria-current="true"' : ''; ?>
                    ><?php echo $fluentEventIcon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>

    <?php if ( 'calendar' === $view ) : ?>
        <?php // Outside the results, so it stays put while a new range loads in place. ?>
        <div class="fe-calendar-head">
            <h2 class="fe-calendar-head__title"><?php echo esc_html( $grid['label'] ); ?></h2>

            <nav class="fe-switch" aria-label="<?php esc_attr_e( 'Switch between month, week and day view', 'fluent-event' ); ?>">
                <?php foreach ( $fluentEventSpans as $fluentEventKey => $fluentEventLabel ) : ?>
                    <a
                        class="fe-switch__option<?php echo $fluentEventKey === $grid['span'] ? ' fe-switch__option--active' : ''; ?>"
                        href="<?php echo esc_url( $fluentEventLink( [ 'span' => 'month' === $fluentEventKey ? '' : $fluentEventKey ] ) ); ?>"
                        <?php echo $fluentEventKey === $grid['span'] ? 'aria-current="true"' : ''; ?>
                        data-fe-nav="span-<?php echo esc_attr( $fluentEventKey ); ?>"
                    ><?php echo esc_html( $fluentEventLabel ); ?></a>
                <?php endforeach; ?>
            </nav>

            <nav class="fe-calendar-head__nav" aria-label="<?php esc_attr_e( 'Change dates', 'fluent-event' ); ?>">
                <a class="fe-calendar-head__step" href="<?php echo esc_url( $fluentEventLink( [ 'date' => $grid['prev'] ] ) ); ?>" aria-label="<?php esc_attr_e( 'Previous', 'fluent-event' ); ?>" title="<?php esc_attr_e( 'Previous', 'fluent-event' ); ?>" data-fe-nav="prev">
                    <?php echo Icon::CHEVRON_LEFT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                </a>
                <a class="fe-calendar-head__today" href="<?php echo esc_url( $fluentEventLink( [ 'date' => '' ] ) ); ?>" data-fe-nav="today"><?php esc_html_e( 'Today', 'fluent-event' ); ?></a>
                <a class="fe-calendar-head__step" href="<?php echo esc_url( $fluentEventLink( [ 'date' => $grid['next'] ] ) ); ?>" aria-label="<?php esc_attr_e( 'Next', 'fluent-event' ); ?>" title="<?php esc_attr_e( 'Next', 'fluent-event' ); ?>" data-fe-nav="next">
                    <?php echo Icon::CHEVRON_RIGHT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                </a>
            </nav>
        </div>
    <?php endif; ?>

    <?php // What portal.js shows in the results while a chip's list loads: the current view's shape, in grey. ?>
    <template data-fe-skeleton>
        <?php if ( 'calendar' === $view ) : ?>
            <span class="fe-skeleton fe-skeleton--calendar" aria-hidden="true"></span>
        <?php elseif ( 'timeline' === $view ) : ?>
            <ol class="fe-timeline" aria-hidden="true">
                <?php for ( $fluentEventIndex = 0; $fluentEventIndex < 3; $fluentEventIndex++ ) : ?>
                    <li class="fe-timeline__item">
                        <span class="fe-timeline__stamp">
                            <span class="fe-skeleton fe-skeleton--line fe-skeleton--short"></span>
                        </span>
                        <div class="fe-event-tile fe-event-tile--row">
                            <div class="fe-event-tile__body">
                                <span class="fe-skeleton fe-skeleton--line fe-skeleton--short"></span>
                                <span class="fe-skeleton fe-skeleton--line fe-skeleton--title"></span>
                                <span class="fe-skeleton fe-skeleton--line fe-skeleton--medium"></span>
                            </div>
                            <span class="fe-event-tile__cover fe-skeleton"></span>
                        </div>
                    </li>
                <?php endfor; ?>
            </ol>
        <?php else : ?>
            <ul class="fe-event-grid" aria-hidden="true">
                <?php for ( $fluentEventIndex = 0; $fluentEventIndex < 4; $fluentEventIndex++ ) : ?>
                    <li class="fe-event-tile">
                        <span class="fe-event-tile__cover fe-skeleton"></span>
                        <div class="fe-event-tile__body">
                            <span class="fe-skeleton fe-skeleton--line fe-skeleton--short"></span>
                            <span class="fe-skeleton fe-skeleton--line fe-skeleton--medium"></span>
                            <span class="fe-skeleton fe-skeleton--line fe-skeleton--title"></span>
                            <span class="fe-skeleton fe-skeleton--line fe-skeleton--medium"></span>
                        </div>
                    </li>
                <?php endfor; ?>
            </ul>
        <?php endif; ?>
    </template>

    <div class="fe-events-page__results" data-fe-results>
        <?php if ( 'calendar' === $view && 'month' === $grid['span'] ) : ?>
            <section class="fe-month" aria-label="<?php echo esc_attr( $grid['label'] ); ?>">
                <div class="fe-month__grid">
                    <?php foreach ( array_slice( $grid['days'], 0, 7 ) as $fluentEventDay ) : ?>
                        <span class="fe-month__weekday"><?php echo esc_html( $fluentEventDay['name'] ); ?></span>
                    <?php endforeach; ?>

                    <?php foreach ( $grid['days'] as $fluentEventDay ) : ?>
                        <div class="fe-month__cell<?php echo $fluentEventDay['outside'] ? ' fe-month__cell--outside' : ''; ?><?php echo $fluentEventDay['today'] ? ' fe-month__cell--today' : ''; ?>">
                            <span class="fe-month__day"><?php echo esc_html( number_format_i18n( $fluentEventDay['number'] ) ); ?></span>

                            <?php foreach ( array_slice( $fluentEventDay['entries'], 0, 3 ) as $fluentEventItem ) : ?>
                                <?php $fluentEventEntry( $fluentEventItem, 'fe-month__chip' ); ?>
                            <?php endforeach; ?>

                            <?php if ( count( $fluentEventDay['entries'] ) > 3 ) : ?>
                                <?php $fluentEventPopover = 'fe-month-day-' . $fluentEventDay['date']; ?>
                                <button type="button" class="fe-month__more" popovertarget="<?php echo esc_attr( $fluentEventPopover ); ?>">
                                    <?php
                                    /* translators: %d: number of further events that day */
                                    echo esc_html( sprintf( __( '+%d more', 'fluent-event' ), count( $fluentEventDay['entries'] ) - 3 ) );
                                    ?>
                                </button>

                                <div id="<?php echo esc_attr( $fluentEventPopover ); ?>" class="fe-month__popover" popover aria-label="<?php echo esc_attr( $fluentEventDay['label'] ); ?>">
                                    <p class="fe-month__popover-title"><?php echo esc_html( $fluentEventDay['label'] ); ?></p>

                                    <?php foreach ( $fluentEventDay['entries'] as $fluentEventItem ) : ?>
                                        <?php $fluentEventEntry( $fluentEventItem, 'fe-month__chip' ); ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ( $fluentEventDay['entries'] ) : ?>
                                <span class="fe-month__dot" aria-hidden="true"></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

        <?php elseif ( 'calendar' === $view ) : ?>
            <?php // The admin's week and day views: a column per day, an hour per row, each event a block from start to end. ?>
            <section class="fe-week<?php echo 'day' === $grid['span'] ? ' fe-week--day' : ''; ?>" aria-label="<?php echo esc_attr( $grid['label'] ); ?>">
                <div class="fe-week__frame">
                    <div class="fe-week__head">
                        <span aria-hidden="true"></span>

                        <?php foreach ( $grid['days'] as $fluentEventDay ) : ?>
                            <div class="fe-week__day<?php echo $fluentEventDay['today'] ? ' fe-week__day--today' : ''; ?>">
                                <span class="fe-week__day-name"><?php echo esc_html( $fluentEventDay['name'] ); ?></span>
                                <span class="fe-week__day-number"><?php echo esc_html( number_format_i18n( $fluentEventDay['number'] ) ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="fe-week__body">
                        <div class="fe-week__times" aria-hidden="true">
                            <?php foreach ( $grid['hours'] as $fluentEventHour ) : ?>
                                <span class="fe-week__time"><?php echo esc_html( $fluentEventHour ); ?></span>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ( $grid['days'] as $fluentEventDay ) : ?>
                            <div class="fe-week__col">
                                <?php foreach ( $grid['hours'] as $fluentEventHour ) : ?>
                                    <span class="fe-week__cell" aria-hidden="true"></span>
                                <?php endforeach; ?>

                                <?php foreach ( $fluentEventDay['entries'] as $fluentEventItem ) : ?>
                                    <?php $fluentEventEntry( $fluentEventItem, 'fe-week__event', 'top: ' . $fluentEventItem['top'] . 'px; height: ' . $fluentEventItem['height'] . 'px; --fe-lane: ' . $fluentEventItem['lane'] . '; --fe-lanes: ' . $fluentEventItem['lanes'] . ';' ); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

        <?php elseif ( ! $rows ) : ?>
            <p class="fe-events-page__empty">
                <?php echo esc_html( '' !== $search ? __( 'No events match your search.', 'fluent-event' ) : $fluentEventEmpty[ $filter ] ); ?>
            </p>

        <?php elseif ( 'timeline' === $view ) : ?>
            <ol class="fe-timeline">
                <?php foreach ( $rows as $fluentEventRow ) : ?>
                    <li class="fe-timeline__item">
                        <span class="fe-timeline__stamp">
                            <span class="fe-timeline__date"><?php echo esc_html( $fluentEventRow['date'] ); ?></span>
                            <span class="fe-timeline__weekday"><?php echo esc_html( $fluentEventRow['weekday'] ); ?></span>
                        </span>

                        <div class="fe-event-tile fe-event-tile--row">
                            <div class="fe-event-tile__body">
                                <?php if ( $show_calendar ) : ?>
                                    <span class="fe-event-tile__calendar">
                                        <span class="fe-event-tile__calendar-dot"<?php echo $fluentEventTint( $fluentEventRow['color'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure ?> aria-hidden="true"></span>
                                        <?php echo esc_html( $fluentEventRow['calendar'] ); ?>
                                    </span>
                                <?php endif; ?>
                                <span class="fe-event-tile__time"><?php echo esc_html( $fluentEventRow['time_range'] ); ?></span>
                                <h3 class="fe-event-tile__title"><a class="fe-event-tile__link" href="<?php echo esc_url( $fluentEventRow['url'] ); ?>"><?php echo esc_html( $fluentEventRow['title'] ); ?></a></h3>
                                <span class="fe-event-tile__location">
                                    <?php echo $fluentEventRow['is_online'] ? Icon::VIDEO : Icon::MAP_PIN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constants ?>
                                    <?php echo esc_html( $fluentEventRow['location'] ); ?>
                                </span>
                            </div>

                            <?php if ( $fluentEventRow['cover_url'] ) : ?>
                                <img class="fe-event-tile__cover" src="<?php echo esc_url( $fluentEventRow['cover_url'] ); ?>" alt="" loading="lazy">
                            <?php else : ?>
                                <span class="fe-event-tile__cover fe-event-tile__cover--empty"<?php echo $fluentEventTint( $fluentEventRow['color'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure ?>></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>

        <?php else : ?>
            <ul class="fe-event-grid">
                <?php foreach ( $rows as $fluentEventRow ) : ?>
                    <li class="fe-event-tile">
                        <?php if ( $fluentEventRow['cover_url'] ) : ?>
                            <img class="fe-event-tile__cover" src="<?php echo esc_url( $fluentEventRow['cover_url'] ); ?>" alt="" loading="lazy">
                        <?php else : ?>
                            <span class="fe-event-tile__cover fe-event-tile__cover--empty"<?php echo $fluentEventTint( $fluentEventRow['color'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure ?>></span>
                        <?php endif; ?>

                        <div class="fe-event-tile__body">
                            <?php if ( $show_calendar ) : ?>
                                <span class="fe-event-tile__calendar">
                                    <span class="fe-event-tile__calendar-dot"<?php echo $fluentEventTint( $fluentEventRow['color'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure ?> aria-hidden="true"></span>
                                    <?php echo esc_html( $fluentEventRow['calendar'] ); ?>
                                </span>
                            <?php endif; ?>
                            <span class="fe-event-tile__date"><?php echo esc_html( $fluentEventRow['date'] ); ?></span>
                            <span class="fe-event-tile__time"><?php echo esc_html( $fluentEventRow['time_range'] ); ?></span>
                            <h3 class="fe-event-tile__title"><a class="fe-event-tile__link" href="<?php echo esc_url( $fluentEventRow['url'] ); ?>"><?php echo esc_html( $fluentEventRow['title'] ); ?></a></h3>
                            <span class="fe-event-tile__location">
                                <?php echo $fluentEventRow['is_online'] ? Icon::VIDEO : Icon::MAP_PIN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constants ?>
                                <?php echo esc_html( $fluentEventRow['location'] ); ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ( 'calendar' !== $view && $last_page > 1 ) : ?>
            <nav class="fe-pagination" aria-label="<?php esc_attr_e( 'Pages', 'fluent-event' ); ?>">
                <?php if ( $current_page > 1 ) : ?>
                    <a class="fe-pagination__link" href="<?php echo esc_url( $fluentEventLink( [ $fluentEventPageVar => $current_page - 1 ] ) ); ?>" rel="prev">
                        <?php echo Icon::CHEVRON_LEFT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                        <span><?php esc_html_e( 'Previous', 'fluent-event' ); ?></span>
                    </a>
                <?php endif; ?>

                <span class="fe-pagination__status">
                    <?php
                    /* translators: 1: current page, 2: number of pages */
                    echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'fluent-event' ), $current_page, $last_page ) );
                    ?>
                </span>

                <?php if ( $current_page < $last_page ) : ?>
                    <a class="fe-pagination__link" href="<?php echo esc_url( $fluentEventLink( [ $fluentEventPageVar => $current_page + 1 ] ) ); ?>" rel="next">
                        <span><?php esc_html_e( 'Next', 'fluent-event' ); ?></span>
                        <?php echo Icon::CHEVRON_RIGHT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
