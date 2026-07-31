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

window.toggleColorMode = function () {
    const wpBodyContent = document.getElementById('wpbody-content');
    if (!wpBodyContent) return;
    const isDark = wpBodyContent.classList.contains('dark');

    wpBodyContent.classList.toggle('dark', !isDark);
    document.documentElement.classList.toggle('dark', !isDark);

    localStorage.setItem('fe_color_mode', isDark ? 'light' : 'dark');
};

(function applyStoredColorMode() {
    const wpBodyContent = document.getElementById('wpbody-content');
    if (!wpBodyContent) return;

    const stored = localStorage.getItem('fe_color_mode') || 'light';
    if (!stored) return;

    document.documentElement.classList.add(stored);
    wpBodyContent?.classList.add(stored);
})();
