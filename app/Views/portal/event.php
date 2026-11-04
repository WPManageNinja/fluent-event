<?php
/**
 * One event's page, laid out like the community event page: details on the left, and a card
 * with when, where and how to join on the right (above the details on narrow screens).
 *
 * @var array      $event            EventFormatter::formatEvent() shape.
 * @var string     $description      kses'd in Event::getDescriptionAttribute().
 * @var string     $calendar_title
 * @var string     $calendar_url
 * @var bool       $is_online
 * @var string     $location
 * @var string     $site_date
 * @var string     $site_time
 * @var string     $chip_month
 * @var string     $chip_day
 * @var string     $date_label
 * @var string     $time_label
 * @var string     $local_time_label
 * @var string     $phase            upcoming|live|ended
 * @var string     $status_label
 * @var string     $count_label
 * @var string     $meeting_link     http(s) only; empty unless this viewer may see it.
 * @var array|null $add_to_calendar  google, ics, filename.
 */
if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

use FluentEvent\App\Services\Icon;
?>
<div class="fe-event-page">
    <a class="fe-event-page__back" href="<?php echo esc_url( $calendar_url ); ?>">
        <?php echo Icon::ARROW_LEFT; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
        <span><?php echo esc_html( $calendar_title ); ?></span>
    </a>

    <?php if ( $event['cover_url'] ) : ?>
        <img class="fe-event-page__cover" src="<?php echo esc_url( $event['cover_url'] ); ?>" alt="">
    <?php endif; ?>

    <div class="fe-event-page__grid">
        <div class="fe-event-page__main">
            <div class="fe-event-page__intro">
                <h1 class="fe-event-page__title"><?php echo esc_html( $event['title'] ); ?></h1>

                <ul class="fe-event-page__meta">
                    <li class="fe-event-page__meta-item">
                        <?php echo Icon::CALENDAR; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                        <span><?php echo esc_html( $site_date ); ?></span>
                    </li>
                    <li class="fe-event-page__meta-item">
                        <?php echo Icon::CLOCK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                        <span><?php echo esc_html( $site_time ); ?></span>
                    </li>
                    <li class="fe-event-page__meta-item">
                        <?php echo $is_online ? Icon::VIDEO : Icon::MAP_PIN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constants ?>
                        <span><?php echo esc_html( $location ); ?></span>
                    </li>
                </ul>
            </div>

            <?php if ( $description ) : ?>
                <section class="fe-event-page__card fe-event-page__body">
                    <h2 class="fe-event-page__section-title"><?php esc_html_e( 'About This Event', 'fluent-event' ); ?></h2>
                    <div class="fe-event-page__description"><?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd in Event::getDescriptionAttribute() ?></div>
                </section>
            <?php endif; ?>
        </div>

        <aside class="fe-event-page__card fe-event-page__side" aria-label="<?php esc_attr_e( 'Registration', 'fluent-event' ); ?>">
            <div class="fe-event-page__when">
                <span class="fe-date-chip" aria-hidden="true">
                    <span class="fe-date-chip__month"><?php echo esc_html( $chip_month ); ?></span>
                    <span class="fe-date-chip__day"><?php echo esc_html( $chip_day ); ?></span>
                </span>

                <div class="fe-event-page__when-text">
                    <span class="fe-event-page__when-date"><?php echo esc_html( $date_label ); ?></span>
                    <span><?php echo esc_html( $time_label ); ?></span>
                    <?php if ( $local_time_label ) : ?>
                        <span class="fe-event-page__when-local"><?php echo esc_html( $local_time_label ); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( 'live' === $phase ) : ?>
                <span class="fe-live-badge"><?php esc_html_e( 'Happening now', 'fluent-event' ); ?></span>
            <?php else : ?>
                <span class="fe-event-page__status fe-event-page__status--<?php echo esc_attr( $phase ); ?>"><?php echo esc_html( $status_label ); ?></span>
            <?php endif; ?>

            <?php if ( $count_label ) : ?>
                <span class="fe-event-page__count"><?php echo esc_html( $count_label ); ?></span>
            <?php endif; ?>

            <?php if ( ! $is_online && ( $event['venue'] || $event['address'] ) ) : ?>
                <div class="fe-event-page__place">
                    <?php echo Icon::MAP_PIN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                    <div class="fe-event-page__place-text">
                        <?php if ( $event['venue'] ) : ?>
                            <span class="fe-event-page__venue"><?php echo esc_html( $event['venue'] ); ?></span>
                        <?php endif; ?>
                        <?php if ( $event['address'] ) : ?>
                            <span class="fe-event-page__address"><?php echo esc_html( $event['address'] ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ( $is_online && 'ended' !== $phase ) : ?>
                <?php if ( $meeting_link ) : ?>
                    <a class="fe-event-page__join" href="<?php echo esc_url( $meeting_link ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo Icon::VIDEO; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                        <span><?php esc_html_e( 'Join online', 'fluent-event' ); ?></span>
                    </a>
                <?php elseif ( $event['has_meeting_url'] ) : ?>
                    <p class="fe-event-page__note"><?php esc_html_e( 'Please register for the event to see the meeting link.', 'fluent-event' ); ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $add_to_calendar ) : ?>
                <div class="fe-event-page__calendar">
                    <span class="fe-event-page__calendar-label"><?php esc_html_e( 'Add to calendar', 'fluent-event' ); ?></span>
                    <div class="fe-event-page__calendar-links">
                        <?php // esc_attr, not esc_url: both are built here from encoded parts, and esc_url strips the encoded line breaks they carry. ?>
                        <a class="fe-event-page__calendar-link" href="<?php echo esc_attr( $add_to_calendar['google'] ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo Icon::GOOGLE; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                            <?php esc_html_e( 'Google Calendar', 'fluent-event' ); ?>
                        </a>
                        <a class="fe-event-page__calendar-link" href="<?php echo esc_attr( $add_to_calendar['ics'] ); ?>" download="<?php echo esc_attr( $add_to_calendar['filename'] ); ?>">
                            <?php echo Icon::APPLE; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                            <?php esc_html_e( 'Apple / Outlook', 'fluent-event' ); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>
