<?php
/**
 * One calendar's page: its header, then its public events as cards, a timeline or a month.
 * Every control is a plain link, so each state has its own URL and works without JavaScript.
 *
 * @var \FluentEvent\App\Models\Calendar $calendar
 * @var string|null                      $color          Theme hex, sanitized.
 * @var int                              $upcoming_count
 * @var string                           $url            This page, without query args.
 * @var string                           $list_url       The calendars list.
 * @var string                           $view           cards|timeline|calendar
 * @var bool                             $view_locked
 * @var string                           $filter         upcoming|all|past
 * @var string                           $search
 * @var array                            $state          Query args every link carries.
 * @var array                            $rows           List views: one formatted row per event.
 * @var int                              $current_page
 * @var int                              $last_page
 * @var array                            $month          Calendar view.
 */
if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

use FluentEvent\App\Services\Icon;

$fluentEventTint = $color ? ' style="color: ' . esc_attr( $color ) . '"' : '';

// add_query_arg() leaves values raw, so encode them; empty values drop out of the link.
$fluentEventLink = function ( array $args ) use ( $state, $url ) {
    return add_query_arg( array_map( 'rawurlencode', array_filter( array_merge( $state, $args ), 'strlen' ) ), $url );
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
<span class="fe-calendar-page__wash"<?php echo $fluentEventTint; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?> aria-hidden="true"></span>

<div class="fe-calendar-page">
    <a class="fe-calendar-page__back" href="<?php echo esc_url( $list_url ); ?>">
        <?php echo Icon::ARROW_LEFT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
        <span><?php esc_html_e( 'All calendars', 'fluent-event' ); ?></span>
    </a>

    <header class="fe-calendar-page__header">
        <div class="fe-calendar-page__cover"<?php echo $fluentEventTint; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>>
            <span class="fe-calendar-page__mark" aria-hidden="true"><?php echo esc_html( mb_substr( trim( $calendar->title ), 0, 2 ) ); ?></span>
        </div>

        <div class="fe-calendar-page__intro">
            <h1 class="fe-calendar-page__title"><?php echo esc_html( $calendar->title ); ?></h1>

            <?php if ( $calendar->description ) : ?>
                <p class="fe-calendar-page__description"><?php echo esc_html( $calendar->description ); ?></p>
            <?php endif; ?>

            <p class="fe-calendar-page__meta">
                <?php echo Icon::CALENDAR; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                <?php
                /* translators: %d: number of upcoming events */
                echo esc_html( sprintf( _n( '%d upcoming event', '%d upcoming events', (int) $upcoming_count, 'fluent-event' ), (int) $upcoming_count ) );
                ?>
            </p>
        </div>
    </header>

    <div class="fe-calendar-page__toolbar">
        <form class="fe-calendar-page__search" method="get" action="<?php echo esc_url( $url ); ?>" role="search">
            <input
                type="search"
                name="search"
                class="fe-calendar-page__search-input"
                value="<?php echo esc_attr( $search ); ?>"
                placeholder="<?php esc_attr_e( 'Search events', 'fluent-event' ); ?>"
                aria-label="<?php esc_attr_e( 'Search events', 'fluent-event' ); ?>"
            >
            <?php foreach ( array_diff_key( $state, [ 'search' => '' ] ) as $fluentEventKey => $fluentEventValue ) : ?>
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
                            <span class="fe-month__chip">
                                <span class="fe-month__chip-time"><?php echo esc_html( $fluentEventRow['time'] ); ?></span>
                                <?php echo esc_html( $fluentEventRow['title'] ); ?>
                            </span>
                        <?php endforeach; ?>

                        <?php if ( count( $fluentEventDayRows ) > 3 ) : ?>
                            <span class="fe-month__more">
                                <?php
                                /* translators: %d: number of further events that day */
                                echo esc_html( sprintf( __( '+%d more', 'fluent-event' ), count( $fluentEventDayRows ) - 3 ) );
                                ?>
                            </span>
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

            <?php if ( ! $month['by_day'] ) : ?>
                <p class="fe-calendar-page__empty"><?php esc_html_e( 'No events this month.', 'fluent-event' ); ?></p>
            <?php endif; ?>
        </section>

    <?php elseif ( ! $rows ) : ?>
        <p class="fe-calendar-page__empty">
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
                            <span class="fe-event-tile__time"><?php echo esc_html( $fluentEventRow['time_range'] ); ?></span>
                            <h3 class="fe-event-tile__title"><?php echo esc_html( $fluentEventRow['title'] ); ?></h3>
                            <span class="fe-event-tile__location">
                                <?php echo $fluentEventRow['is_online'] ? Icon::VIDEO : Icon::MAP_PIN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constants ?>
                                <?php echo esc_html( $fluentEventRow['location'] ); ?>
                            </span>
                        </div>

                        <?php if ( $fluentEventRow['cover_url'] ) : ?>
                            <img class="fe-event-tile__cover" src="<?php echo esc_url( $fluentEventRow['cover_url'] ); ?>" alt="" loading="lazy">
                        <?php else : ?>
                            <span class="fe-event-tile__cover fe-event-tile__cover--empty"<?php echo $fluentEventTint; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>></span>
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
                        <span class="fe-event-tile__cover fe-event-tile__cover--empty"<?php echo $fluentEventTint; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>></span>
                    <?php endif; ?>

                    <div class="fe-event-tile__body">
                        <span class="fe-event-tile__date"><?php echo esc_html( $fluentEventRow['date'] ); ?></span>
                        <span class="fe-event-tile__time"><?php echo esc_html( $fluentEventRow['time_range'] ); ?></span>
                        <h3 class="fe-event-tile__title"><?php echo esc_html( $fluentEventRow['title'] ); ?></h3>
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
