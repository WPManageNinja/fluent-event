<?php
/**
 * One piece of an event's page. The single-event template's blocks place each on its own; event.php puts them
 * together for [fluent_event]. Same variables as event.php, plus:
 *
 * @var string $fluentEventPart cover|title|meta|calendar|description
 */
defined('ABSPATH') || exit;

use FluentEvent\App\Services\Icon;
?>
<?php if ( 'cover' === $fluentEventPart && $event['cover_url'] ) : ?>
    <img class="fe-event-page__cover" src="<?php echo esc_url( $event['cover_url'] ); ?>" alt="">
<?php elseif ( 'title' === $fluentEventPart ) : ?>
    <h1 class="fe-event-page__title"><?php echo esc_html( $event['title'] ); ?></h1>
<?php elseif ( 'meta' === $fluentEventPart ) : ?>
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
<?php elseif ( 'calendar' === $fluentEventPart ) : ?>
    <div class="fe-calendar-chips fe-event-page__calendar-tag">
        <a class="fe-calendar-chips__chip" href="<?php echo esc_url( $calendar_url ); ?>">
            <?php if ( $calendar_color ) : ?>
                <span class="fe-calendar-chips__dot" style="color: <?php echo esc_attr( $calendar_color ); ?>" aria-hidden="true"></span>
            <?php endif; ?>
            <span class="fe-calendar-chips__label" title="<?php echo esc_attr( $calendar_title ); ?>"><?php echo esc_html( $calendar_title ); ?></span>
        </a>
    </div>
<?php elseif ( 'description' === $fluentEventPart && $description ) : ?>
    <section class="fe-event-page__card fe-event-page__body">
        <h2 class="fe-event-page__section-title"><?php esc_html_e( 'About This Event', 'fluent-event' ); ?></h2>
        <div class="fe-event-page__description"><?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd in Event::getDescriptionAttribute() ?></div>
    </section>
<?php endif; ?>
