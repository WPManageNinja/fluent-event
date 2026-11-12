<?php
/**
 * An event's own address, or the events archive, in a classic theme: its header and footer around ours.
 * Block themes get the same from the single-fe_event and archive-fe_event templates.
 */
if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

$fluentEventPage = new \FluentEvent\App\Hooks\Handlers\FrontEndHandler();

get_header();

// Each render escapes its own output in the portal views.
echo is_singular( \FluentEvent\App\CPT\EventPost::POST_TYPE ) ? $fluentEventPage->renderEvent( [] ) : $fluentEventPage->renderEvents( [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

get_footer();
