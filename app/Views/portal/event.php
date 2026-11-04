<?php
/**
 * One event's page, laid out like the community event page: details on the left, and the side card
 * with when, where and how to join on the right (above the details on narrow screens).
 *
 * @var array  $event          EventService::formatEvent() shape.
 * @var string $description    kses'd in Event::getDescriptionAttribute().
 * @var string $calendar_title
 * @var string $calendar_url
 * @var bool   $is_online
 * @var string $location
 * @var string $site_date
 * @var string $site_time
 * @var string $side           The rendered side card (portal/event-side.php).
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

        <?php echo $side; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by EventPageService::sideCard(), escaped there ?>
    </div>
</div>
