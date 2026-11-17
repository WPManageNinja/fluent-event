<?php
/**
 * One event's page, laid out like the community event page: details on the left, and the side card
 * with when, where and how to join on the right (above the details on narrow screens). Each piece
 * comes from event-part.php, which the single-event template's blocks also render one by one.
 *
 * @var array  $event          EventService::formatEvent() shape.
 * @var string $description    kses'd in Event::getDescriptionAttribute().
 * @var string $calendar_title
 * @var string $calendar_url   The events home filtered to this calendar.
 * @var string $calendar_color Sanitized hex or ''.
 * @var bool   $is_online
 * @var string $location
 * @var string $site_date
 * @var string $site_time
 * @var string $side           The rendered side card (portal/event-side.php).
 */
defined('ABSPATH') || exit;
?>
<div class="fe-event-page">
    <?php $fluentEventPart = 'cover'; include __DIR__ . '/event-part.php'; ?>

    <div class="fe-event-page__grid">
        <div class="fe-event-page__main">
            <div class="fe-event-page__intro">
                <?php foreach ( [ 'title', 'meta', 'calendar' ] as $fluentEventPart ) : ?>
                    <?php include __DIR__ . '/event-part.php'; ?>
                <?php endforeach; ?>
            </div>

            <?php $fluentEventPart = 'description'; include __DIR__ . '/event-part.php'; ?>
        </div>

        <?php echo $side; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by EventPageService::sideCard(), escaped there ?>
    </div>
</div>
