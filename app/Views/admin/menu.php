<?php
/**
 * Admin topbar template. Variables are extracted from the array passed
 * by AdminMenuHandler::render() via the framework view renderer.
 *
 * @var string $slug           Plugin slug.
 * @var string $baseUrl        SPA base URL (admin.php?page=...#/).
 * @var string $name           Plugin display name.
 * @var string $logo           Light-mode logo URL.
 * @var string $darkLogo       Dark-mode logo URL.
 * @var array  $menuItems      Center topbar items.
 * @var array  $rightMenuItems Right topbar items (may carry 'children').
 */
defined('ABSPATH') || exit;

use FluentEvent\App\Services\Icon;
?>
<div id="<?php echo esc_attr( $slug ); ?>-app" class="fe-app">

    <div class="fe-navbar">
        <button type="button"
                class="fe-navbar__menu-toggle"
                aria-label="<?php echo esc_attr__( 'Toggle menu', 'fluent-event' ); ?>"
                aria-expanded="false"
                aria-controls="fe-navbar-links"
                onclick="fluentEventToggleMobileMenu()">
            <span class="el-icon"><?php echo Icon::MENU; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
        </button>

        <div class="fe-navbar__logo">
            <a href="<?php echo esc_url( $baseUrl ); ?>">
                <img class="fe-navbar__logo-img fe-navbar__logo-img--light"
                     src="<?php echo esc_url( $logo ); ?>"
                     alt="<?php echo esc_attr( $name ); ?>" />
                <img class="fe-navbar__logo-img fe-navbar__logo-img--dark"
                     src="<?php echo esc_url( $darkLogo ); ?>"
                     alt="<?php echo esc_attr( $name ); ?>" />
            </a>
        </div>

        <div class="fe-navbar__links" id="fe-navbar-links">
            <?php foreach ( $menuItems as $fluentEventMenuItem ) : ?>
                <div class="fe-navbar__link-wrapper"
                     data-key="<?php echo esc_attr( $fluentEventMenuItem['key'] ); ?>">
                    <a class="fe-navbar__link"
                       href="<?php echo esc_url( $fluentEventMenuItem['permalink'] ); ?>">
                        <span class="fe-navbar__link-text"><?php
                            echo esc_html( $fluentEventMenuItem['label'] );
                        ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="fe-navbar__settings">
            <div class="fe-navbar__link-wrapper" data-key="color_mode">
                <button type="button"
                        class="fe-navbar__link fe-navbar__color-mode"
                        aria-label="<?php echo esc_attr__( 'Color theme', 'fluent-event' ); ?>"
                        title="<?php echo esc_attr__( 'Color theme', 'fluent-event' ); ?>"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        aria-controls="fe-navbar-color-mode-menu"
                        onclick="fluentEventToggleNavbarDropdown('fe-navbar-color-mode-menu')">
                    <span class="el-icon">
                        <span class="fe-navbar__mode-icon fe-navbar__mode-icon--light"><?php echo Icon::SUN; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                        <span class="fe-navbar__mode-icon fe-navbar__mode-icon--dark"><?php echo Icon::MOON; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                        <span class="fe-navbar__mode-icon fe-navbar__mode-icon--system"><?php echo Icon::SYSTEM; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                    </span>
                </button>

                <div class="fe-navbar__dropdown"
                     id="fe-navbar-color-mode-menu"
                     role="menu"
                     aria-label="<?php echo esc_attr__( 'Color theme', 'fluent-event' ); ?>">
                    <?php
                    $fluentEventColorModes = [
                        'light'  => [ __( 'Light', 'fluent-event' ), Icon::SUN ],
                        'dark'   => [ __( 'Dark', 'fluent-event' ), Icon::MOON ],
                        'system' => [ __( 'System', 'fluent-event' ), Icon::SYSTEM ],
                    ];
                    foreach ( $fluentEventColorModes as $fluentEventMode => $fluentEventModeItem ) : ?>
                        <button type="button"
                                class="fe-navbar__link fe-navbar__mode-item"
                                role="menuitemradio"
                                aria-checked="false"
                                data-mode="<?php echo esc_attr( $fluentEventMode ); ?>"
                                onclick="fluentEventSetColorMode('<?php echo esc_attr( $fluentEventMode ); ?>')">
                            <span class="el-icon"><?php echo $fluentEventModeItem[1]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                            <span class="fe-navbar__link-text"><?php echo esc_html( $fluentEventModeItem[0] ); ?></span>
                            <span class="fe-navbar__mode-check"><?php echo Icon::CHECK; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php foreach ( $rightMenuItems as $fluentEventRightMenuItem ) : ?>
                <div class="fe-navbar__link-wrapper"
                     data-key="<?php echo esc_attr( $fluentEventRightMenuItem['key'] ); ?>">
                    <?php if ( ! empty( $fluentEventRightMenuItem['children'] ) ) : ?>
                        <a class="fe-navbar__link fe-navbar__link--direct"
                           href="<?php echo esc_url( $fluentEventRightMenuItem['permalink'] ); ?>">
                            <span class="el-icon"><?php echo Icon::SETTING; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                            <span class="fe-navbar__link-text"><?php
                                echo esc_html( $fluentEventRightMenuItem['label'] );
                            ?></span>
                        </a>

                        <button type="button"
                                class="fe-navbar__link fe-navbar__link--toggle"
                                aria-label="<?php echo esc_attr( $fluentEventRightMenuItem['label'] ); ?>"
                                aria-expanded="false"
                                aria-controls="fe-navbar-<?php echo esc_attr( $fluentEventRightMenuItem['key'] ); ?>-menu"
                                onclick="fluentEventToggleNavbarDropdown('fe-navbar-<?php echo esc_attr( $fluentEventRightMenuItem['key'] ); ?>-menu')">
                            <span class="el-icon"><?php echo Icon::SETTING; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                            <span class="fe-navbar__link-text"><?php
                                echo esc_html( $fluentEventRightMenuItem['label'] );
                            ?></span>
                        </button>

                        <div class="fe-navbar__dropdown"
                             id="fe-navbar-<?php echo esc_attr( $fluentEventRightMenuItem['key'] ); ?>-menu">
                            <?php foreach ( $fluentEventRightMenuItem['children'] as $fluentEventChildItem ) : ?>
                                <a class="fe-navbar__link"
                                   data-key="<?php echo esc_attr( $fluentEventChildItem['key'] ); ?>"
                                   href="<?php echo esc_url( $fluentEventChildItem['permalink'] ); ?>">
                                    <span class="fe-navbar__link-text"><?php
                                        echo esc_html( $fluentEventChildItem['label'] );
                                    ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <a class="fe-navbar__link"
                           href="<?php echo esc_url( $fluentEventRightMenuItem['permalink'] ); ?>">
                            <span class="el-icon"><?php echo Icon::SETTING; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG constant ?></span>
                            <span class="fe-navbar__link-text"><?php
                                echo esc_html( $fluentEventRightMenuItem['label'] );
                            ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <div class="fe-app__body">
        <div id="<?php echo esc_attr( $slug ); ?>-router" class="fe-app__canvas"></div>
    </div>
</div>
