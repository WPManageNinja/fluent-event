<?php
/**
 * [fluent_event]'s 404: an unknown, private or draft event.
 *
 * @var string $description
 * @var string $portal_url
 */
defined('ABSPATH') || exit;
?>
<div class="fe-not-found">
    <p class="fe-not-found__code">404</p>
    <h1 class="fe-not-found__title"><?php esc_html_e( 'Page not found', 'fluent-event' ); ?></h1>
    <p class="fe-not-found__text"><?php echo esc_html( $description ); ?></p>
    <a class="fe-not-found__button" href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'Browse events', 'fluent-event' ); ?></a>
</div>
