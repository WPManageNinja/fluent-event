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

window.toggleColorMode = function () {
    const mode = document.documentElement.classList.contains('dark') ? 'light' : 'dark';

    applyColorMode(mode);
    localStorage.setItem('fe_color_mode', mode);
};

applyColorMode(localStorage.getItem('fe_color_mode') === 'dark' ? 'dark' : 'light');

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

window.toggleMobileMenu = function () {
    setMobileMenu(!isMobileMenuOpen());
};

document.addEventListener('click', (event) => {
    if (!isMobileMenuOpen() || event.target.closest('.fe-navbar__menu-toggle')) return;
    setMobileMenu(false);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !isMobileMenuOpen()) return;
    setMobileMenu(false);
    navbarToggle?.focus();
});
