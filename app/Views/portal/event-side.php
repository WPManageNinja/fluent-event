<?php
/**
 * The event page's side card. Rendered with the page and again by the registration endpoint, whose
 * answer portal.js swaps in, so every state — Register, the RSVP menu, the final mark — is drawn here.
 * A logged-in viewer gets the member's card, as on the portal: their own answer, which they can change.
 *
 * @var array      $event            EventService::formatEvent() shape, with a logged-in viewer's own status.
 * @var bool       $is_online
 * @var string     $chip_month
 * @var string     $chip_day
 * @var string     $date_label
 * @var string     $time_label
 * @var string     $local_time_label
 * @var string     $phase            upcoming|live|ended
 * @var string     $status_label
 * @var int|null   $countdown_to     The start, as a Unix timestamp, when the event page counts down to it.
 * @var int        $ends_at          The end, as a Unix timestamp.
 * @var string     $count_label
 * @var array|null $meter            percent, status; the count against the capacity, when both show.
 * @var array|null $notice           type (full|waitlisted), title, text.
 * @var string     $meeting_link     http(s) only; empty unless this viewer may see it.
 * @var array|null $registration     EventPageService::registration(); null when the event takes none now.
 * @var bool       $join_waitlist    The event is full, so registering joins its waitlist.
 * @var array|null $add_to_calendar  google, ics, filename.
 * @var string     $answer           'registered', 'joined_waitlist', 'already_registered', 'waitlisted' or 'cancelled'
 *                                   in the registration's answer, which portal.js opens; '' on the page.
 */
defined('ABSPATH') || exit;

use FluentEvent\App\Services\Icon;

$fluentEventId = (int) $event['id'];
$fluentEventOpen = 'open' === $event['entry_type'];
$fluentEventMember = $registration && $registration['is_member'];
$fluentEventStatus = $registration ? $registration['status'] : '';
// A visitor's ✓ Going and ✓ Registered are final: there is no account to change them from.
$fluentEventDone = $registration && $registration['is_going'] && ! $fluentEventMember;
// The name and email form: open events not yet registered for, and a visitor's RSVP.
$fluentEventForm = $registration && ! $registration['is_going'] && 'waitlisted' !== $fluentEventStatus && ( $fluentEventOpen || ! $fluentEventMember );
$fluentEventRsvpLabels = [
    'going'     => __( 'Going', 'fluent-event' ),
    'not_going' => __( 'Not going', 'fluent-event' ),
    'waitlisted' => __( 'Waitlisted', 'fluent-event' ),
    ''          => __( 'Interested?', 'fluent-event' ),
];
$fluentEventAction = $join_waitlist ? __( 'Join waitlist', 'fluent-event' ) : __( 'Register', 'fluent-event' );
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
        <?php if ( $fluentEventMember ) : ?>
            data-url="<?php echo esc_url( $registration['member_url'] ); ?>"
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
    <?php elseif ( $countdown_to ) : ?>
        <?php // portal.js fills it in and ticks it down, then puts the "Happening now" badge (or Ended, past the end) in its place at 0. ?>
        <div
            class="fe-countdown"
            role="timer"
            aria-label="<?php esc_attr_e( 'Time until the event starts', 'fluent-event' ); ?>"
            data-fe-countdown="<?php echo esc_attr( $countdown_to ); ?>"
            data-fe-countdown-end="<?php echo esc_attr( $ends_at ); ?>"
            data-live-label="<?php esc_attr_e( 'Happening now', 'fluent-event' ); ?>"
            data-ended-label="<?php esc_attr_e( 'Ended', 'fluent-event' ); ?>"
        >
            <?php foreach ( [ 'days' => __( 'Days', 'fluent-event' ), 'hours' => __( 'Hours', 'fluent-event' ), 'minutes' => __( 'Minutes', 'fluent-event' ), 'seconds' => __( 'Seconds', 'fluent-event' ) ] as $fluentEventUnit => $fluentEventUnitLabel ) : ?>
                <span class="fe-countdown__unit">
                    <span class="fe-countdown__value" data-unit="<?php echo esc_attr( $fluentEventUnit ); ?>">--</span>
                    <span class="fe-countdown__label"><?php echo esc_html( $fluentEventUnitLabel ); ?></span>
                </span>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <span class="fe-event-page__status fe-event-page__status--<?php echo esc_attr( $phase ); ?>"><?php echo esc_html( $status_label ); ?></span>
    <?php endif; ?>

    <?php if ( $meter ) : ?>
        <div class="fe-capacity">
            <div class="fe-capacity__head">
                <span><?php echo esc_html( $count_label ); ?></span>
                <?php if ( $meter['status'] ) : ?>
                    <span class="fe-capacity__status"><?php echo esc_html( $meter['status'] ); ?></span>
                <?php endif; ?>
            </div>
            <div
                class="fe-capacity__track"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="<?php echo esc_attr( $event['capacity'] ); ?>"
                aria-valuenow="<?php echo esc_attr( $event['guest_count'] ); ?>"
                aria-valuetext="<?php echo esc_attr( $count_label ); ?>"
                aria-label="<?php esc_attr_e( 'Capacity', 'fluent-event' ); ?>"
            >
                <span class="fe-capacity__fill" style="width: <?php echo esc_attr( $meter['percent'] ); ?>%"></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( $notice ) : ?>
        <?php // Focused after joining the waitlist, like the ✓ badge after registering. ?>
        <div
            class="fe-event-page__notice fe-event-page__notice--<?php echo esc_attr( $notice['type'] ); ?>"
            <?php if ( 'waitlisted' === $notice['type'] ) : ?>
                tabindex="-1" data-fe-focus
            <?php endif; ?>
        >
            <span class="fe-event-page__notice-icon">
                <?php echo 'waitlisted' === $notice['type'] ? Icon::CLOCK : Icon::TICKET; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constants ?>
            </span>
            <span class="fe-event-page__notice-text">
                <span class="fe-event-page__notice-title"><?php echo esc_html( $notice['title'] ); ?></span>
                <span class="fe-event-page__notice-desc"><?php echo esc_html( $notice['text'] ); ?></span>
            </span>
        </div>
    <?php endif; ?>

    <?php if ( $registration || ( $count_label && ! $meter ) ) : ?>
        <div class="fe-event-page__rsvp">
            <?php if ( $fluentEventDone ) : ?>
                <span class="fe-event-page__badge" tabindex="-1" data-fe-focus>
                    <span class="fe-event-page__badge-icon"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                    <?php echo esc_html( $fluentEventOpen ? __( 'Registered', 'fluent-event' ) : __( 'Going', 'fluent-event' ) ); ?>
                </span>
            <?php elseif ( $fluentEventOpen && $fluentEventMember && 'waitlisted' === $fluentEventStatus ) : ?>
                <?php // An RSVP's waitlist place is an answer in its own menu below. ?>
                <button type="button" class="fe-rsvp__trigger" popovertarget="fe-rsvp-<?php echo esc_attr( $fluentEventId ); ?>" aria-haspopup="menu" data-fe-focus>
                    <span class="fe-rsvp__icon fe-rsvp__icon--waitlisted"><?php echo Icon::CLOCK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                    <span class="fe-rsvp__label"><?php esc_html_e( 'On the waitlist', 'fluent-event' ); ?></span>
                    <span class="fe-rsvp__chevron"><?php echo Icon::CHEVRON_DOWN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                </button>

                <div id="fe-rsvp-<?php echo esc_attr( $fluentEventId ); ?>" class="fe-rsvp__menu" popover role="menu" aria-label="<?php esc_attr_e( 'Waitlist', 'fluent-event' ); ?>">
                    <button type="button" class="fe-rsvp__option" role="menuitem" data-fe-status="not_going">
                        <span class="fe-rsvp__icon fe-rsvp__icon--not-going"><?php echo Icon::CROSS; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                        <?php esc_html_e( 'Leave waitlist', 'fluent-event' ); ?>
                    </button>
                </div>
            <?php elseif ( $fluentEventOpen && $fluentEventMember && 'going' === $fluentEventStatus ) : ?>
                <button type="button" class="fe-rsvp__trigger" popovertarget="fe-rsvp-<?php echo esc_attr( $fluentEventId ); ?>" aria-haspopup="menu" data-fe-focus>
                    <span class="fe-rsvp__icon fe-rsvp__icon--going"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                    <span class="fe-rsvp__label"><?php esc_html_e( 'Registered', 'fluent-event' ); ?></span>
                    <span class="fe-rsvp__chevron"><?php echo Icon::CHEVRON_DOWN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                </button>

                <div id="fe-rsvp-<?php echo esc_attr( $fluentEventId ); ?>" class="fe-rsvp__menu" popover role="menu" aria-label="<?php esc_attr_e( 'Registration', 'fluent-event' ); ?>">
                    <button type="button" class="fe-rsvp__option" role="menuitem" data-fe-status="not_going">
                        <span class="fe-rsvp__icon fe-rsvp__icon--not-going"><?php echo Icon::CROSS; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                        <?php esc_html_e( 'Not going', 'fluent-event' ); ?>
                    </button>
                </div>
            <?php elseif ( $registration && ( $fluentEventOpen || ( $join_waitlist && ! $fluentEventMember ) ) ) : ?>
                <button type="button" class="fe-event-page__button" popovertarget="fe-register-<?php echo esc_attr( $fluentEventId ); ?>" data-fe-focus>
                    <?php echo esc_html( $fluentEventAction ); ?>
                </button>
            <?php elseif ( $registration ) : ?>
                <button type="button" class="fe-rsvp__trigger" popovertarget="fe-rsvp-<?php echo esc_attr( $fluentEventId ); ?>" aria-haspopup="menu" data-fe-focus>
                    <?php if ( 'going' === $fluentEventStatus ) : ?>
                        <span class="fe-rsvp__icon fe-rsvp__icon--going"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                    <?php elseif ( 'waitlisted' === $fluentEventStatus ) : ?>
                        <span class="fe-rsvp__icon fe-rsvp__icon--waitlisted"><?php echo Icon::CLOCK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                    <?php elseif ( 'not_going' === $fluentEventStatus ) : ?>
                        <span class="fe-rsvp__icon fe-rsvp__icon--not-going"><?php echo Icon::CROSS; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                    <?php endif; ?>
                    <span class="fe-rsvp__label"><?php echo esc_html( $fluentEventRsvpLabels[ $fluentEventStatus ] ); ?></span>
                    <span class="fe-rsvp__chevron"><?php echo Icon::CHEVRON_DOWN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                </button>

                <div id="fe-rsvp-<?php echo esc_attr( $fluentEventId ); ?>" class="fe-rsvp__menu" popover role="menu" aria-label="<?php esc_attr_e( 'RSVP', 'fluent-event' ); ?>">
                    <?php if ( $fluentEventMember ) : ?>
                        <?php // A member answers by account, straight away, with no form. ?>
                        <?php // A waitlisted guest's place is checked here; only an admin moves them to going. ?>
                        <?php if ( $join_waitlist || 'waitlisted' === $fluentEventStatus ) : ?>
                            <button type="button" class="fe-rsvp__option" role="menuitemradio" aria-checked="<?php echo 'waitlisted' === $fluentEventStatus ? 'true' : 'false'; ?>" data-fe-status="going">
                                <span class="fe-rsvp__icon fe-rsvp__icon--waitlisted"><?php echo Icon::CLOCK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                                <?php echo esc_html( 'waitlisted' === $fluentEventStatus ? __( 'Waitlisted', 'fluent-event' ) : __( 'Join waitlist', 'fluent-event' ) ); ?>
                            </button>
                        <?php else : ?>
                            <button type="button" class="fe-rsvp__option" role="menuitemradio" aria-checked="<?php echo 'going' === $fluentEventStatus ? 'true' : 'false'; ?>" data-fe-status="going">
                                <span class="fe-rsvp__icon fe-rsvp__icon--going"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                                <?php esc_html_e( 'Going', 'fluent-event' ); ?>
                            </button>
                        <?php endif; ?>
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

            <?php if ( $count_label && ! $meter ) : ?>
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
            action="<?php echo esc_url( $registration['url'] ); ?>"
            aria-labelledby="fe-register-title-<?php echo esc_attr( $fluentEventId ); ?>"
            data-fe-register
        >
            <div class="fe-register__head">
                <div>
                    <h2 class="fe-register__title" id="fe-register-title-<?php echo esc_attr( $fluentEventId ); ?>"><?php echo esc_html( $fluentEventAction ); ?></h2>
                    <p class="fe-register__event"><?php echo esc_html( $event['title'] ); ?></p>
                </div>
                <button type="button" class="fe-register__close" popovertarget="fe-register-<?php echo esc_attr( $fluentEventId ); ?>" popovertargetaction="hide" aria-label="<?php esc_attr_e( 'Close', 'fluent-event' ); ?>">
                    <?php echo Icon::CROSS; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
                </button>
            </div>

            <label class="fe-register__label" for="fe-register-name-<?php echo esc_attr( $fluentEventId ); ?>"><?php esc_html_e( 'Name', 'fluent-event' ); ?></label>
            <input id="fe-register-name-<?php echo esc_attr( $fluentEventId ); ?>" class="fe-register__input" type="text" name="name" autocomplete="name" required autofocus value="<?php echo esc_attr( $registration['name'] ); ?>">

            <label class="fe-register__label" for="fe-register-email-<?php echo esc_attr( $fluentEventId ); ?>"><?php esc_html_e( 'Email', 'fluent-event' ); ?></label>
            <?php // A member's account email comes filled in; keeping it registers them by account, another registers that email. ?>
            <input id="fe-register-email-<?php echo esc_attr( $fluentEventId ); ?>" class="fe-register__input" type="email" name="email" autocomplete="email" required value="<?php echo esc_attr( $registration['email'] ); ?>">

            <input class="fe-register__trap" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

            <p class="fe-register__error" role="alert"></p>

            <button type="submit" class="fe-event-page__button"><?php echo esc_html( $fluentEventAction ); ?></button>
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
        <?php elseif ( $event['has_meeting_url'] && $registration && ! $join_waitlist && 'waitlisted' !== $fluentEventStatus ) : ?>
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

    <?php if ( 'registered' === $answer ) : ?>
        <div
            id="fe-registered-<?php echo esc_attr( $fluentEventId ); ?>"
            class="fe-register-success"
            popover
            role="dialog"
            aria-labelledby="fe-registered-title-<?php echo esc_attr( $fluentEventId ); ?>"
            data-fe-answer
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
    <?php elseif ( 'joined_waitlist' === $answer ) : ?>
        <div
            id="fe-registered-<?php echo esc_attr( $fluentEventId ); ?>"
            class="fe-register-success"
            popover
            role="dialog"
            aria-labelledby="fe-registered-title-<?php echo esc_attr( $fluentEventId ); ?>"
            aria-describedby="fe-registered-text-<?php echo esc_attr( $fluentEventId ); ?>"
            data-fe-answer
        >
            <button type="button" class="fe-register__close fe-register-success__close" popovertarget="fe-registered-<?php echo esc_attr( $fluentEventId ); ?>" popovertargetaction="hide" aria-label="<?php esc_attr_e( 'Close', 'fluent-event' ); ?>">
                <?php echo Icon::CROSS; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
            </button>

            <div class="fe-register-success__head">
                <span class="fe-register-success__check"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                <h2 class="fe-register-success__title" id="fe-registered-title-<?php echo esc_attr( $fluentEventId ); ?>"><?php esc_html_e( "You're on the waitlist", 'fluent-event' ); ?></h2>
                <p class="fe-register-success__text" id="fe-registered-text-<?php echo esc_attr( $fluentEventId ); ?>"><?php esc_html_e( 'The host will let you know if a spot opens up.', 'fluent-event' ); ?></p>
            </div>

            <button type="button" class="fe-event-page__button" popovertarget="fe-registered-<?php echo esc_attr( $fluentEventId ); ?>" popovertargetaction="hide" autofocus>
                <?php esc_html_e( 'Done', 'fluent-event' ); ?>
            </button>
        </div>
    <?php elseif ( in_array( $answer, array( 'already_registered', 'cancelled', 'waitlisted' ), true ) ) : ?>
        <div
            id="fe-registered-<?php echo esc_attr( $fluentEventId ); ?>"
            class="fe-register-success fe-register-success--warning"
            popover
            role="alertdialog"
            aria-labelledby="fe-registered-title-<?php echo esc_attr( $fluentEventId ); ?>"
            aria-describedby="fe-registered-text-<?php echo esc_attr( $fluentEventId ); ?>"
            data-fe-answer
        >
            <button type="button" class="fe-register__close fe-register-success__close" popovertarget="fe-registered-<?php echo esc_attr( $fluentEventId ); ?>" popovertargetaction="hide" aria-label="<?php esc_attr_e( 'Close', 'fluent-event' ); ?>">
                <?php echo Icon::CROSS; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?>
            </button>

            <div class="fe-register-success__head">
                <span class="fe-register-success__check"><?php echo Icon::WARNING; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                <h2 class="fe-register-success__title" id="fe-registered-title-<?php echo esc_attr( $fluentEventId ); ?>">
                    <?php
                    $fluentEventWarnings = array(
                        'cancelled'          => array( __( 'Your registration was cancelled', 'fluent-event' ), __( 'Contact the host if you want to join again.', 'fluent-event' ) ),
                        'waitlisted'         => array( __( "You're already on the waitlist", 'fluent-event' ), __( 'The host will let you know if a spot opens up.', 'fluent-event' ) ),
                        'already_registered' => array( __( 'This email is already registered', 'fluent-event' ), __( 'Check your inbox for the confirmation email, or register with a different email.', 'fluent-event' ) ),
                    );
                    echo esc_html( $fluentEventWarnings[ $answer ][0] );
                    ?>
                </h2>
                <p class="fe-register-success__text" id="fe-registered-text-<?php echo esc_attr( $fluentEventId ); ?>">
                    <?php echo esc_html( $fluentEventWarnings[ $answer ][1] ); ?>
                </p>
            </div>

            <button type="button" class="fe-event-page__button" popovertarget="fe-registered-<?php echo esc_attr( $fluentEventId ); ?>" popovertargetaction="hide" autofocus>
                <?php esc_html_e( 'OK', 'fluent-event' ); ?>
            </button>
        </div>
    <?php endif; ?>
</aside>
