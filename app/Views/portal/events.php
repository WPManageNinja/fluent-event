<?php
/**
 * The landing home: every public calendar's events, or one calendar's, as cards, a timeline or a
 * month, with a chip per calendar to switch. Every control is a plain link, so each state has its own
 * URL and works without JavaScript; portal.js swaps the list in place when a chip is picked.
 *
 * @var array  $chips          title, slug ('' for All), color (sanitized hex or ''), active. Empty with one calendar.
 * @var bool   $show_calendar  Name each event's calendar: there are chips to switch between them.
 * @var string $url            The landing home, without query args.
 * @var string $view           cards|timeline|calendar
 * @var bool   $view_locked
 * @var string $filter         upcoming|all|past
 * @var string $search
 * @var array  $state          Query args every link carries.
 * @var array  $rows           List views: one eventRow() per event.
 * @var int    $current_page
 * @var int    $last_page
 * @var array  $month          Calendar view.
 */
if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

use FluentEvent\App\Services\Icon;

// A calendar's colour, inherited as currentColor by its dot or an empty cover.
$fluentEventTint = function ( $color ) {
    return $color ? ' style="color: ' . esc_attr( $color ) . '"' : '';
};

// add_query_arg() leaves values raw, so encode them; empty values drop out of the link.
$fluentEventLink = function ( array $args ) use ( $state, $url ) {
    return add_query_arg( array_map( 'rawurlencode', array_filter( array_merge( $state, $args ), 'strlen' ) ), $url );
};

// The month being viewed isn't in $state, so picking a calendar or searching keeps it only through this.
$fluentEventMonth = 'calendar' === $view ? [ 'month' => $month['value'] ] : [];

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
    <header class="fe-portal__header">
        <h1 class="fe-portal__title"><?php esc_html_e( 'Events', 'fluent-event' ); ?></h1>
        <p class="fe-portal__subtitle"><?php esc_html_e( 'Events from our calendars.', 'fluent-event' ); ?></p>
    </header>

    <?php if ( $chips ) : ?>
        <?php // Every chip in one row; on wide screens portal.js moves those that don't fit into the "More" panel. ?>
        <div class="fe-calendar-chips" data-fe-chips>
            <nav class="fe-calendar-chips__row" aria-label="<?php esc_attr_e( 'Calendars', 'fluent-event' ); ?>" data-fe-chips-row>
                <?php foreach ( $chips as $fluentEventChip ) : ?>
                    <a
                        class="fe-calendar-chips__chip<?php echo $fluentEventChip['active'] ? ' fe-calendar-chips__chip--active' : ''; ?>"
                        href="<?php echo esc_url( $fluentEventLink( [ 'calendar' => $fluentEventChip['slug'] ] + $fluentEventMonth ) ); ?>"
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
                name="search"
                class="fe-events-page__search-input"
                value="<?php echo esc_attr( $search ); ?>"
                placeholder="<?php esc_attr_e( 'Search events', 'fluent-event' ); ?>"
                aria-label="<?php esc_attr_e( 'Search events', 'fluent-event' ); ?>"
            >
            <?php foreach ( array_diff_key( $state, [ 'search' => '' ] ) + $fluentEventMonth as $fluentEventKey => $fluentEventValue ) : ?>
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

    <?php // What portal.js shows in the results while a chip's list loads: the current view's shape, in grey. ?>
    <template data-fe-skeleton>
        <?php if ( 'calendar' === $view ) : ?>
            <span class="fe-skeleton fe-skeleton--month" aria-hidden="true"></span>
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
        <?php if ( 'calendar' === $view ) : ?>
            <section class="fe-month" aria-label="<?php echo esc_attr( $month['label'] ); ?>">
                <div class="fe-month__head">
                    <h2 class="fe-month__title"><?php echo esc_html( $month['label'] ); ?></h2>
                    <nav class="fe-month__nav" aria-label="<?php esc_attr_e( 'Change month', 'fluent-event' ); ?>">
                        <a class="fe-month__nav-link" href="<?php echo esc_url( $fluentEventLink( [ 'month' => $month['prev'] ] ) ); ?>" aria-label="<?php esc_attr_e( 'Previous month', 'fluent-event' ); ?>">
                            <?php echo Icon::CHEVRON_LEFT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                        </a>
                        <a class="fe-month__nav-link" href="<?php echo esc_url( $fluentEventLink( [ 'month' => $month['next'] ] ) ); ?>" aria-label="<?php esc_attr_e( 'Next month', 'fluent-event' ); ?>">
                            <?php echo Icon::CHEVRON_RIGHT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                        </a>
                    </nav>
                </div>

                <div class="fe-month__grid">
                    <?php foreach ( $month['weekdays'] as $fluentEventWeekday ) : ?>
                        <span class="fe-month__weekday"><?php echo esc_html( $fluentEventWeekday ); ?></span>
                    <?php endforeach; ?>

                    <?php for ( $fluentEventCell = 0; $fluentEventCell < $month['lead']; $fluentEventCell++ ) : ?>
                        <span class="fe-month__cell fe-month__cell--blank"></span>
                    <?php endfor; ?>

                    <?php for ( $fluentEventDay = 1; $fluentEventDay <= $month['days']; $fluentEventDay++ ) : ?>
                        <?php $fluentEventDayRows = $month['by_day'][ $fluentEventDay ] ?? []; ?>
                        <div class="fe-month__cell<?php echo $fluentEventDay === $month['today'] ? ' fe-month__cell--today' : ''; ?>">
                            <span class="fe-month__day"><?php echo esc_html( number_format_i18n( $fluentEventDay ) ); ?></span>

                            <?php foreach ( array_slice( $fluentEventDayRows, 0, 3 ) as $fluentEventRow ) : ?>
                                <a class="fe-month__chip" href="<?php echo esc_url( $fluentEventRow['url'] ); ?>">
                                    <span class="fe-month__chip-time"><?php echo esc_html( $fluentEventRow['time'] ); ?></span>
                                    <?php echo esc_html( $fluentEventRow['title'] ); ?>
                                </a>
                            <?php endforeach; ?>

                            <?php if ( count( $fluentEventDayRows ) > 3 ) : ?>
                                <?php $fluentEventPopover = 'fe-month-day-' . $fluentEventDay; ?>
                                <button type="button" class="fe-month__more" popovertarget="<?php echo esc_attr( $fluentEventPopover ); ?>">
                                    <?php
                                    /* translators: %d: number of further events that day */
                                    echo esc_html( sprintf( __( '+%d more', 'fluent-event' ), count( $fluentEventDayRows ) - 3 ) );
                                    ?>
                                </button>

                                <div id="<?php echo esc_attr( $fluentEventPopover ); ?>" class="fe-month__popover" popover aria-label="<?php echo esc_attr( $month['day_labels'][ $fluentEventDay ] ); ?>">
                                    <p class="fe-month__popover-title"><?php echo esc_html( $month['day_labels'][ $fluentEventDay ] ); ?></p>

                                    <?php foreach ( $fluentEventDayRows as $fluentEventRow ) : ?>
                                        <a class="fe-month__chip" href="<?php echo esc_url( $fluentEventRow['url'] ); ?>">
                                            <span class="fe-month__chip-time"><?php echo esc_html( $fluentEventRow['time'] ); ?></span>
                                            <?php echo esc_html( $fluentEventRow['title'] ); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ( $fluentEventDayRows ) : ?>
                                <span class="fe-month__dot" aria-hidden="true"></span>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>

                    <?php for ( $fluentEventCell = ( $month['lead'] + $month['days'] ) % 7; $fluentEventCell && $fluentEventCell < 7; $fluentEventCell++ ) : ?>
                        <span class="fe-month__cell fe-month__cell--blank"></span>
                    <?php endfor; ?>
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
                    <a class="fe-pagination__link" href="<?php echo esc_url( $fluentEventLink( [ 'page' => $current_page - 1 ] ) ); ?>" rel="prev">
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
                    <a class="fe-pagination__link" href="<?php echo esc_url( $fluentEventLink( [ 'page' => $current_page + 1 ] ) ); ?>" rel="next">
                        <span><?php esc_html_e( 'Next', 'fluent-event' ); ?></span>
                        <?php echo Icon::CHEVRON_RIGHT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
