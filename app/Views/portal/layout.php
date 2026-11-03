<?php
/**
 * Standalone document for the public landing pages. No theme and no wp_head(),
 * so nothing enqueued prints here — assets are linked directly.
 *
 * @var string $title
 * @var string $description
 * @var string $url
 * @var string $site_name
 * @var string $site_icon
 * @var string $style_url
 * @var string $body        Rendered page view, escaped where it was built.
 */
if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

use FluentEvent\App\Services\Icon;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( $title ); ?></title>
    <meta name="description" content="<?php echo esc_attr( $description ); ?>">
    <link rel="canonical" href="<?php echo esc_url( $url ); ?>">

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
    <meta property="og:description" content="<?php echo esc_attr( $description ); ?>">
    <meta property="og:url" content="<?php echo esc_url( $url ); ?>">
    <meta property="og:site_name" content="<?php echo esc_attr( $site_name ); ?>">

    <?php wp_site_icon(); ?>

    <?php // Runs before the body paints, so a stored dark mode never flashes light first. ?>
    <script>
        document.documentElement.classList.toggle('dark', localStorage.getItem('fe_portal_color_mode') === 'dark');

        function toggleColorMode() {
            var isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('fe_portal_color_mode', isDark ? 'dark' : 'light');
        }
    </script>

    <?php if ( $style_url ) : ?>
        <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
        <link rel="stylesheet" href="<?php echo esc_url( $style_url . '?ver=' . rawurlencode( FLUENT_EVENT_PLUGIN_VERSION ) ); ?>">
    <?php endif; ?>

    <?php do_action( 'fluent_event/portal/head' ); ?>
</head>
<body class="fe-portal">
    <header class="fe-portal__topbar">
        <a class="fe-portal__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
            <?php if ( $site_icon ) : ?>
                <img class="fe-portal__brand-icon" src="<?php echo esc_url( $site_icon ); ?>" alt="">
            <?php endif; ?>
            <span><?php echo esc_html( $site_name ); ?></span>
        </a>

        <button type="button"
                class="fe-portal__mode-toggle"
                aria-label="<?php echo esc_attr__( 'Toggle dark mode', 'fluent-event' ); ?>"
                onclick="toggleColorMode()">
            <span class="fe-portal__mode-icon fe-portal__mode-icon--light"><?php echo Icon::MOON; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
            <span class="fe-portal__mode-icon fe-portal__mode-icon--dark"><?php echo Icon::SUN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
        </button>
    </header>

    <main class="fe-portal__main">
        <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in its own view ?>
    </main>

    <?php do_action( 'fluent_event/portal/footer' ); ?>
</body>
</html>
