import app from '@/bootstrap';
import { capitalize } from 'vue';

const globals = app.vue.config.globalProperties;

jQuery(document).on('heartbeat-tick', (event, data) => {
	const slug = globals.appVars.slug;
    if (data[slug]) {
    	if (typeof wpApiSettings !== 'undefined') {
            wpApiSettings.nonce = data[slug];
        }
		globals.appVars.rest.nonce = data[slug];
    }
});

// -------------------------------------
// Color theme: Light, Dark or System
// -------------------------------------
// Shared with every Fluent plugin through `fluent_theme_mode` (`light`, `dark`, `system:<resolved>`).
const THEME_KEY = 'fluent_theme_mode';
const DARK_CLASS = 'fluent_theme_dark';
const COLOR_MODES = ['light', 'dark', 'system'];
const systemDark = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

const readColorMode = () => {
    let mode = '';
    try {
        mode = String(localStorage.getItem(THEME_KEY) || '').split(':')[0];
    } catch (e) {}

    return COLOR_MODES.includes(mode) ? mode : 'system';
};

const applyColorMode = (mode, store = true) => {
    const resolved = mode === 'system' ? (systemDark?.matches ? 'dark' : 'light') : mode;
    const isDark = resolved === 'dark';
    const root = document.documentElement;

    root.classList.toggle(DARK_CLASS, isDark);
    root.classList.toggle('dark', isDark);
    root.setAttribute('data-fe-theme', mode);
    document.body.classList.toggle(DARK_CLASS, isDark);

    document.querySelectorAll('.fe-navbar__mode-item').forEach((item) => {
        item.setAttribute('aria-checked', String(item.dataset.mode === mode));
    });

    if (store) {
        try {
            localStorage.setItem(THEME_KEY, mode === 'system' ? `system:${resolved}` : mode);
        } catch (e) {}
    }
};

window.addEventListener('storage', (event) => {
    if (event.key === THEME_KEY) {
        applyColorMode(readColorMode(), false);
    }
});

systemDark?.addEventListener?.('change', () => {
    if (readColorMode() === 'system') {
        applyColorMode('system');
    }
});

applyColorMode(readColorMode());


// -------------------------------------
// Toggle mobile menu
// -------------------------------------
const navbarLinks = document.getElementById('fe-navbar-links');
const navbarToggle = document.querySelector('.fe-navbar__menu-toggle');

const isMobileMenuOpen = () =>
    navbarLinks?.classList.contains('fe-navbar__links--open');

const setMobileMenu = (open) => {
    navbarLinks?.classList.toggle('fe-navbar__links--open', open);
    navbarToggle?.setAttribute('aria-expanded', String(open));
};

// -------------------------------------
// Toggle navbar dropdowns (the Settings menu)
// -------------------------------------
const dropdownTrigger = (menu) =>
    menu.parentElement?.querySelector(`[aria-controls="${menu.id}"]`);

const menuItems = (menu) => [...menu.querySelectorAll('[role^="menuitem"]')];

const setNavbarDropdown = (menu, open) => {
    menu.classList.toggle('fe-navbar__dropdown--open', open);
    dropdownTrigger(menu)?.setAttribute('aria-expanded', String(open));

    if (open && menu.getAttribute('role') === 'menu') {
        (menu.querySelector('[aria-checked="true"]') || menuItems(menu)[0])?.focus();
    }
};

const closeNavbarDropdowns = () => {
    document
        .querySelectorAll('.fe-navbar__dropdown--open')
        .forEach((menu) => setNavbarDropdown(menu, false));
};

// -------------------------------------
// Global window functions for mobile menu and dropdowns
// -------------------------------------
window.fluentEventToggleMobileMenu = function () {
    setMobileMenu(!isMobileMenuOpen());
};

window.fluentEventSetColorMode = function (mode) {
    if (!COLOR_MODES.includes(mode)) return;

    applyColorMode(mode);
    // FluentCart listens on this channel, not the `storage` event.
    if (typeof BroadcastChannel !== 'undefined') {
        const channel = new BroadcastChannel('fluent_theme_changed:' + window.location.origin);
        channel.postMessage({ mode });
        channel.close();
    }
    document.querySelector('[aria-controls="fe-navbar-color-mode-menu"]')?.focus();
};

window.fluentEventToggleNavbarDropdown = function (id) {
    const menu = document.getElementById(id);
    if (!menu) return;

    const open = !menu.classList.contains('fe-navbar__dropdown--open');
    closeNavbarDropdowns();
    setNavbarDropdown(menu, open);
};

// -------------------------------------
// Event listeners for mobile menu and dropdowns
// -------------------------------------
document.addEventListener('click', (event) => {
    if (isMobileMenuOpen() && !event.target.closest('.fe-navbar__menu-toggle')) {
        setMobileMenu(false);
    }

    if (!event.target.closest('.fe-navbar__link--toggle, .fe-navbar__color-mode')) {
        closeNavbarDropdowns();
    }
});

document.addEventListener('keydown', (event) => {
    const openMenu = document.querySelector('.fe-navbar__dropdown--open[role="menu"]');

    if (openMenu && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        event.preventDefault();

        const items = menuItems(openMenu);
        const index = items.indexOf(document.activeElement);
        const step = event.key === 'ArrowDown' ? 1 : -1;
        const next = index === -1 ? (step === 1 ? 0 : items.length - 1) : index + step;

        items[(next + items.length) % items.length]?.focus();
        return;
    }

    if (event.key !== 'Escape') return;

    if (isMobileMenuOpen()) {
        setMobileMenu(false);
        navbarToggle?.focus();
    }

    if (openMenu?.contains(document.activeElement)) {
        dropdownTrigger(openMenu)?.focus();
    }

    closeNavbarDropdowns();
});

document.addEventListener('focusout', (event) => {
    const menu = event.target.closest?.('.fe-navbar__dropdown--open[role="menu"]');
    const wrapper = menu?.parentElement;

    if (wrapper && event.relatedTarget && !wrapper.contains(event.relatedTarget)) {
        setNavbarDropdown(menu, false);
    }
});
