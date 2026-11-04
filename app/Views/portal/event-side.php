<?php
/**
 * The event page's side card. Rendered with the page and again by each registration endpoint, whose
 * answer portal.js swaps in, so every state — Register, the RSVP menu, the final mark — is drawn here.
 *
 * @var array      $event            CommunityEvents::formatEvent() shape, for this viewer.
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
 */
if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

use FluentEvent\App\Services\Icon;

$fluentEventId = (int) $event['id'];
$fluentEventOpen = 'open' === $event['entry_type'];
$fluentEventMember = $registration && $registration['is_member'];
$fluentEventStatus = $registration ? $registration['status'] : '';
// A visitor's ✓ Going is final, like ✓ Registered; a member can still change their RSVP.
$fluentEventDone = 'going' === $fluentEventStatus && ( $fluentEventOpen || ! $fluentEventMember );
// The name and email form: open events, and a visitor's RSVP.
$fluentEventForm = $registration && ! $fluentEventDone && ( $fluentEventOpen || ! $fluentEventMember );
$fluentEventRsvpLabels = [
    'going'     => __( 'Going', 'fluent-event' ),
    'not_going' => __( 'Not going', 'fluent-event' ),
    ''          => __( 'Interested?', 'fluent-event' ),
];
?>
<aside
    class="fe-event-page__card fe-event-page__side"
    aria-label="<?php esc_attr_e( 'Registration', 'fluent-event' ); ?>"
    data-fe-card
    data-error="<?php esc_attr_e( 'Something went wrong. Please try again.', 'fluent-event' ); ?>"
    <?php if ( $fluentEventMember ) : ?>
        data-url="<?php echo esc_url( $registration['member_url'] ); ?>"
        data-nonce="<?php echo esc_attr( $registration['rest_nonce'] ); ?>"
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
                    <?php if ( 'going' === $fluentEventStatus ) : ?>
                        <span class="fe-rsvp__icon fe-rsvp__icon--going"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                    <?php elseif ( 'not_going' === $fluentEventStatus ) : ?>
                        <span class="fe-rsvp__icon fe-rsvp__icon--not-going"><?php echo Icon::CROSS; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                    <?php endif; ?>
                    <span class="fe-rsvp__label"><?php echo esc_html( $fluentEventRsvpLabels[ $fluentEventStatus ] ); ?></span>
                    <span class="fe-rsvp__chevron"><?php echo Icon::CHEVRON_DOWN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                </button>

                <div id="fe-rsvp-<?php echo esc_attr( $fluentEventId ); ?>" class="fe-rsvp__menu" popover role="menu" aria-label="<?php esc_attr_e( 'RSVP', 'fluent-event' ); ?>">
                    <?php if ( $fluentEventMember ) : ?>
                        <button type="button" class="fe-rsvp__option" role="menuitemradio" aria-checked="<?php echo 'going' === $fluentEventStatus ? 'true' : 'false'; ?>" data-fe-status="going">
                            <span class="fe-rsvp__icon fe-rsvp__icon--going"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                            <?php esc_html_e( 'Going', 'fluent-event' ); ?>
                        </button>
                        <button type="button" class="fe-rsvp__option" role="menuitemradio" aria-checked="<?php echo 'not_going' === $fluentEventStatus ? 'true' : 'false'; ?>" data-fe-status="not_going">
                            <span class="fe-rsvp__icon fe-rsvp__icon--not-going"><?php echo Icon::CROSS; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                            <?php esc_html_e( 'Not going', 'fluent-event' ); ?>
                        </button>
                    <?php else : ?>
                        <?php // Visitors only say Going: a not-going row by email would block that email from Going later. ?>
                        <button type="button" class="fe-rsvp__option" role="menuitem" popovertarget="fe-register-<?php echo esc_attr( $fluentEventId ); ?>">
                            <span class="fe-rsvp__icon fe-rsvp__icon--going"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                            <?php esc_html_e( 'Going', 'fluent-event' ); ?>
                        </button>
                    <?php endif; ?>
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

    <?php if ( $fluentEventForm ) : ?>
        <form
            id="fe-register-<?php echo esc_attr( $fluentEventId ); ?>"
            class="fe-register"
            popover
            method="post"
            action="<?php echo esc_url( $fluentEventMember ? $registration['member_url'] : $registration['visitor_url'] ); ?>"
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
            <input id="fe-register-name-<?php echo esc_attr( $fluentEventId ); ?>" class="fe-register__input" type="text" name="name" autocomplete="name" required autofocus value="<?php echo esc_attr( $registration['name'] ); ?>">

            <label class="fe-register__label" for="fe-register-email-<?php echo esc_attr( $fluentEventId ); ?>"><?php esc_html_e( 'Email', 'fluent-event' ); ?></label>
            <?php // A member's email is the account's: shown, locked, and left out of the post. ?>
            <input id="fe-register-email-<?php echo esc_attr( $fluentEventId ); ?>" class="fe-register__input" type="email" name="email" autocomplete="email" required value="<?php echo esc_attr( $registration['email'] ); ?>"<?php disabled( $fluentEventMember ); ?>>

            <?php if ( $fluentEventMember ) : ?>
                <input type="hidden" name="status" value="going">
            <?php else : ?>
                <input type="hidden" name="nonce" value="<?php echo esc_attr( $registration['visitor_nonce'] ); ?>">
                <input class="fe-register__trap" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
            <?php endif; ?>

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
</aside>
