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
// Toggle theme mode 
// -------------------------------------
const applyColorMode = (mode) => {
    const isDark = mode === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.classList.toggle('light', !isDark);
};
const isDarkModeEnabled = window.fluentEventAdmin?.general_settings?.dark_mode !== 'no';

applyColorMode(
    isDarkModeEnabled && localStorage.getItem('fe_color_mode') === 'dark' ? 'dark' : 'light'
);


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
const setNavbarDropdown = (menu, open) => {
    menu.classList.toggle('fe-navbar__dropdown--open', open);
    menu.parentElement
        ?.querySelector(`[aria-controls="${menu.id}"]`)
        ?.setAttribute('aria-expanded', String(open));
};

const closeNavbarDropdowns = () => {
    document
        .querySelectorAll('.fe-navbar__dropdown--open')
        .forEach((menu) => setNavbarDropdown(menu, false));
};

// -------------------------------------
// Global window functions for mobile menu and dropdowns
// -------------------------------------
window.toggleMobileMenu = function () {
    setMobileMenu(!isMobileMenuOpen());
};

window.toggleColorMode = function () {
    const mode = document.documentElement.classList.contains('dark') ? 'light' : 'dark';

    applyColorMode(mode);
    localStorage.setItem('fe_color_mode', mode);
};

window.toggleNavbarDropdown = function (id) {
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

    if (!event.target.closest('.fe-navbar__link--toggle')) {
        closeNavbarDropdowns();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    if (isMobileMenuOpen()) {
        setMobileMenu(false);
        navbarToggle?.focus();
    }

    closeNavbarDropdowns();
});
