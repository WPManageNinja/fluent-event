<?php
/**
 * Landing home: every public calendar.
 *
 * @var \FluentEvent\Framework\Database\Orm\Collection $calendars   Carries upcoming_events_count.
 * @var string                                          $description
 */
if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

use FluentEvent\App\Services\Icon;
use FluentEvent\App\Services\Settings;
use FluentEvent\Framework\Support\Arr;
?>
<div class="fe-portal__header">
    <h1 class="fe-portal__title"><?php esc_html_e( 'Calendars', 'fluent-event' ); ?></h1>
    <p class="fe-portal__subtitle"><?php echo esc_html( $description ); ?></p>
</div>

<?php if ( $calendars->isEmpty() ) : ?>
    <p class="fe-portal__empty"><?php esc_html_e( 'No calendars to show yet.', 'fluent-event' ); ?></p>
<?php else : ?>
    <ul class="fe-portal__grid">
        <?php foreach ( $calendars as $fluentEventCalendar ) : ?>
            <?php $fluentEventColor = sanitize_hex_color( Arr::get( (array) $fluentEventCalendar->meta, 'theme_color', '' ) ); ?>
            <li class="fe-calendar-card">
                <div class="fe-calendar-card__cover"<?php echo $fluentEventColor ? ' style="color: ' . esc_attr( $fluentEventColor ) . '"' : ''; ?>>
                    <span class="fe-calendar-card__mark" aria-hidden="true"><?php echo esc_html( mb_substr( trim( $fluentEventCalendar->title ), 0, 2 ) ); ?></span>
                </div>

                <div class="fe-calendar-card__body">
                    <h2 class="fe-calendar-card__title">
                        <a class="fe-calendar-card__link" href="<?php echo esc_url( home_url( user_trailingslashit( Settings::portalSlug() . '/' . $fluentEventCalendar->slug ) ) ); ?>">
                            <?php echo esc_html( $fluentEventCalendar->title ); ?>
                        </a>
                    </h2>

                    <?php if ( $fluentEventCalendar->description ) : ?>
                        <p class="fe-calendar-card__description">
                            <?php echo esc_html( wp_trim_words( wp_strip_all_tags( $fluentEventCalendar->description ), 24 ) ); ?>
                        </p>
                    <?php endif; ?>

                    <p class="fe-calendar-card__meta">
                        <?php echo Icon::CALENDAR; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                        <?php
                        /* translators: %d: number of upcoming events */
                        echo esc_html( sprintf( _n( '%d upcoming event', '%d upcoming events', (int) $fluentEventCalendar->upcoming_events_count, 'fluent-event' ), (int) $fluentEventCalendar->upcoming_events_count ) );
                        ?>
                    </p>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
