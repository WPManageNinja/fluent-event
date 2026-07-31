<?php
if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

use FluentEvent\App\Services\Icon;
?>
<div id="<?php echo esc_attr( $slug ); ?>-app" class="fe_wrapper">

    <div class="fe-navbar">
        <button type="button"
                class="fe-navbar__menu-toggle"
                aria-label="<?php echo esc_attr__( 'Toggle menu', 'fluent-event' ); ?>"
                aria-expanded="false"
                aria-controls="fe-navbar-links"
                onclick="toggleMobileMenu()">
            <span class="el-icon"><?php echo Icon::MENU; ?></span>
        </button>

        <div class="fe-navbar__logo">
            <a href="<?php echo esc_url( $baseUrl ); ?>">
                <img class="fe-navbar__logo-img fe_light_logo"
                     src="<?php echo esc_url( $logo ); ?>"
                     alt="<?php echo esc_attr( $name ); ?>" />
                <img class="fe-navbar__logo-img fe_dark_logo"
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
                        class="fe-navbar__link fe_color_mode"
                        aria-label="<?php echo esc_attr__( 'Toggle dark mode', 'fluent-event' ); ?>"
                        onclick="toggleColorMode()">
                    <span class="el-icon">
                        <span class="fe_light_icon"><?php echo Icon::MOON; ?></span>
                        <span class="fe_dark_icon"><?php echo Icon::SUN; ?></span>
                    </span>
                </button>
            </div>

            <?php foreach ( $rightMenuItems as $fluentEventRightMenuItem ) : ?>
                <div class="fe-navbar__link-wrapper"
                     data-key="<?php echo esc_attr( $fluentEventRightMenuItem['key'] ); ?>">
                    <a class="fe-navbar__link"
                       href="<?php echo esc_url( $fluentEventRightMenuItem['permalink'] ); ?>">
                        <span class="el-icon"><?php echo Icon::SETTING; ?></span>
                        <span class="fe-navbar__link-text"><?php
                            echo esc_html( $fluentEventRightMenuItem['label'] );
                        ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <div class="fe_body">
        <div id="<?php echo esc_attr( $slug ); ?>-router" class="fe_route_wrapper"></div>
    </div>
</div>
