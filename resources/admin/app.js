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
