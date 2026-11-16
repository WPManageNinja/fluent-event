<?php
/**
 * The event page's side card. Rendered with the page and again by the registration endpoint, whose
 * answer portal.js swaps in, so every state — Register, the RSVP menu, the final mark — is drawn here.
 * Every viewer gets the visitor's card, logged in or not.
 *
 * @var array      $event            EventService::formatEvent() shape, as a visitor sees it.
 * @var bool       $is_online
 * @var string     $chip_month
 * @var string     $chip_day
 * @var string     $date_label
 * @var string     $time_label
 * @var string     $local_time_label
 * @var string     $phase            upcoming|live|ended
 * @var string     $status_label
 * @var string     $count_label
 * @var string     $meeting_link     http(s) only; empty unless this viewer may see it.
 * @var array|null $registration     EventPageService::registration(); null when the event takes none now.
 * @var array|null $add_to_calendar  google, ics, filename.
 * @var bool       $just_registered  Drawn only in the registration's answer, which portal.js opens.
 */
if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

use FluentEvent\App\Services\Icon;

$fluentEventId = (int) $event['id'];
$fluentEventOpen = 'open' === $event['entry_type'];
// ✓ Going and ✓ Registered are final: there is no account to change them from.
$fluentEventDone = $registration && $registration['is_going'];
?>
<aside
    class="fe-event-page__card fe-event-page__side"
    aria-label="<?php esc_attr_e( 'Registration', 'fluent-event' ); ?>"
    data-fe-card
    data-error="<?php esc_attr_e( 'Something went wrong. Please try again.', 'fluent-event' ); ?>"
    <?php if ( $registration ) : ?>
        data-event-nonce="<?php echo esc_attr( $registration['event_nonce'] ); ?>"
        <?php if ( $registration['rest_nonce'] ) : ?>
            data-nonce="<?php echo esc_attr( $registration['rest_nonce'] ); ?>"
        <?php endif; ?>
    <?php endif; ?>
>
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

    <?php if ( $registration || $count_label ) : ?>
        <div class="fe-event-page__rsvp">
            <?php if ( $fluentEventDone ) : ?>
                <span class="fe-event-page__badge" tabindex="-1" data-fe-focus>
                    <span class="fe-event-page__badge-icon"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                    <?php echo esc_html( $fluentEventOpen ? __( 'Registered', 'fluent-event' ) : __( 'Going', 'fluent-event' ) ); ?>
                </span>
            <?php elseif ( $registration && $fluentEventOpen ) : ?>
                <button type="button" class="fe-event-page__button" popovertarget="fe-register-<?php echo esc_attr( $fluentEventId ); ?>" data-fe-focus>
                    <?php esc_html_e( 'Register', 'fluent-event' ); ?>
                </button>
            <?php elseif ( $registration ) : ?>
                <button type="button" class="fe-rsvp__trigger" popovertarget="fe-rsvp-<?php echo esc_attr( $fluentEventId ); ?>" aria-haspopup="menu" data-fe-focus>
                    <span class="fe-rsvp__label"><?php esc_html_e( 'Interested?', 'fluent-event' ); ?></span>
                    <span class="fe-rsvp__chevron"><?php echo Icon::CHEVRON_DOWN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                </button>

                <div id="fe-rsvp-<?php echo esc_attr( $fluentEventId ); ?>" class="fe-rsvp__menu" popover role="menu" aria-label="<?php esc_attr_e( 'RSVP', 'fluent-event' ); ?>">
                    <?php // Only Going: a not-going row by email would block that email from Going later. ?>
                    <button type="button" class="fe-rsvp__option" role="menuitem" popovertarget="fe-register-<?php echo esc_attr( $fluentEventId ); ?>">
                        <span class="fe-rsvp__icon fe-rsvp__icon--going"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                        <?php esc_html_e( 'Going', 'fluent-event' ); ?>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ( $count_label ) : ?>
                <span class="fe-event-page__count"><?php echo esc_html( $count_label ); ?></span>
            <?php endif; ?>
        </div>

        <?php if ( $registration ) : ?>
            <p class="fe-event-page__error" role="alert"></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ( $registration && ! $fluentEventDone ) : ?>
        <form
            id="fe-register-<?php echo esc_attr( $fluentEventId ); ?>"
            class="fe-register"
            popover
            method="post"
            action="<?php echo esc_url( $registration['url'] ); ?>"
            aria-labelledby="fe-register-title-<?php echo esc_attr( $fluentEventId ); ?>"
            data-fe-register
        >
            <div class="fe-register__head">
                <div>
                    <h2 class="fe-register__title" id="fe-register-title-<?php echo esc_attr( $fluentEventId ); ?>"><?php esc_html_e( 'Register', 'fluent-event' ); ?></h2>
                    <p class="fe-register__event"><?php echo esc_html( $event['title'] ); ?></p>
                </div>
                <button type="button" class="fe-register__close" popovertarget="fe-register-<?php echo esc_attr( $fluentEventId ); ?>" popovertargetaction="hide" aria-label="<?php esc_attr_e( 'Close', 'fluent-event' ); ?>">
                    <?php echo Icon::CROSS; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                </button>
            </div>

            <label class="fe-register__label" for="fe-register-name-<?php echo esc_attr( $fluentEventId ); ?>"><?php esc_html_e( 'Name', 'fluent-event' ); ?></label>
            <input id="fe-register-name-<?php echo esc_attr( $fluentEventId ); ?>" class="fe-register__input" type="text" name="name" autocomplete="name" required autofocus>

            <label class="fe-register__label" for="fe-register-email-<?php echo esc_attr( $fluentEventId ); ?>"><?php esc_html_e( 'Email', 'fluent-event' ); ?></label>
            <input id="fe-register-email-<?php echo esc_attr( $fluentEventId ); ?>" class="fe-register__input" type="email" name="email" autocomplete="email" required>

            <input class="fe-register__trap" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

            <p class="fe-register__error" role="alert"></p>

            <button type="submit" class="fe-event-page__button"><?php esc_html_e( 'Register', 'fluent-event' ); ?></button>
        </form>
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

    <?php if ( $just_registered ) : ?>
        <div
            id="fe-registered-<?php echo esc_attr( $fluentEventId ); ?>"
            class="fe-register-success"
            popover
            role="dialog"
            aria-labelledby="fe-registered-title-<?php echo esc_attr( $fluentEventId ); ?>"
            data-fe-registered
        >
            <button type="button" class="fe-register__close fe-register-success__close" popovertarget="fe-registered-<?php echo esc_attr( $fluentEventId ); ?>" popovertargetaction="hide" aria-label="<?php esc_attr_e( 'Close', 'fluent-event' ); ?>">
                <?php echo Icon::CROSS; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
            </button>

            <div class="fe-register-success__head">
                <span class="fe-register-success__check"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                <h2 class="fe-register-success__title" id="fe-registered-title-<?php echo esc_attr( $fluentEventId ); ?>">
                    <?php echo esc_html( $fluentEventOpen ? __( "You're registered", 'fluent-event' ) : __( "You're going", 'fluent-event' ) ); ?>
                </h2>
            </div>

            <div class="fe-register-success__event">
                <?php if ( $event['cover_url'] ) : ?>
                    <img class="fe-register-success__cover" src="<?php echo esc_url( $event['cover_url'] ); ?>" alt="">
                <?php else : ?>
                    <span class="fe-date-chip" aria-hidden="true">
                        <span class="fe-date-chip__month"><?php echo esc_html( $chip_month ); ?></span>
                        <span class="fe-date-chip__day"><?php echo esc_html( $chip_day ); ?></span>
                    </span>
                <?php endif; ?>

                <div class="fe-register-success__details">
                    <span class="fe-register-success__event-title"><?php echo esc_html( $event['title'] ); ?></span>
                    <span class="fe-register-success__meta">
                        <?php echo Icon::CLOCK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                        <?php echo esc_html( $date_label . ' · ' . $time_label ); ?>
                    </span>
                    <span class="fe-register-success__meta">
                        <?php echo $is_online ? Icon::VIDEO : Icon::MAP_PIN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constants ?>
                        <?php echo esc_html( $is_online ? __( 'Online', 'fluent-event' ) : ( $event['venue'] ?: __( 'In person', 'fluent-event' ) ) ); ?>
                    </span>
                </div>
            </div>

            <?php if ( $is_online && $meeting_link ) : ?>
                <a class="fe-event-page__join" href="<?php echo esc_url( $meeting_link ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo Icon::VIDEO; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                    <span><?php esc_html_e( 'Join online', 'fluent-event' ); ?></span>
                </a>
            <?php endif; ?>

            <?php if ( $add_to_calendar ) : ?>
                <div class="fe-event-page__calendar">
                    <span class="fe-event-page__calendar-label"><?php esc_html_e( 'Add to calendar', 'fluent-event' ); ?></span>
                    <div class="fe-event-page__calendar-links">
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

            <button type="button" class="fe-event-page__button" popovertarget="fe-registered-<?php echo esc_attr( $fluentEventId ); ?>" popovertargetaction="hide" autofocus>
                <?php esc_html_e( 'Done', 'fluent-event' ); ?>
            </button>
        </div>
    <?php endif; ?>
</aside>
