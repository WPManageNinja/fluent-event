import app from '@/bootstrap';
import router from '@/router';
import config from '@/bootstrap/config';
import { ElLoading } from 'element-plus';

if (typeof __webpack_public_path__ !== 'undefined') {
	if (config && config.asset_url) {
		__webpack_public_path__ = config.asset_url;
	}
}

app.vue.use(ElLoading);

const appRouter = router(app.vue, config.routes);
app.vue.use(appRouter);

appRouter.afterEach((to) => {
    const activeKey = to.meta?.active_menu;

    jQuery('.fe-navbar__link')
        .removeClass('fe-navbar__link--active')
        .removeAttr('aria-current');

    if (activeKey) {
        jQuery(`.fe-navbar__link-wrapper[data-key="${activeKey}"] > .fe-navbar__link`)
            .addClass('fe-navbar__link--active')
            .attr('aria-current', 'page');
    }
    
    if (to.name) {
        jQuery(`.fe-navbar__dropdown .fe-navbar__link[data-key="${to.name}"]`)
            .addClass('fe-navbar__link--active')
            .attr('aria-current', 'page');
    }

    jQuery('#adminmenu .wp-submenu li.current, #adminmenu .wp-submenu a.current')
        .removeClass('current');

    jQuery('#adminmenu .wp-submenu a[aria-current]').removeAttr('aria-current');

    if (activeKey) {
        const base = (config.baseUrl || '').replace(/#\/?$/, '');
        const page = base.includes('?')
            ? base.slice(base.indexOf('?'))
            : `?page=${config.slug}`;
        const group = activeKey === 'dashboard' ? page : `${page}#/${activeKey}`;
        const exact = jQuery(`#adminmenu .wp-submenu a[href$="${page}${to.href}"]`);
        const match = exact.length
            ? exact
            : jQuery(`#adminmenu .wp-submenu a[href$="${group}"]`);

        match.addClass('current')
            .attr('aria-current', 'page')
            .parent()
            .addClass('current');
    }
});

app.vue.mount(`#${config.slug}-router`);
