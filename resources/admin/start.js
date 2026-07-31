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

    jQuery('.fe-navbar__link').removeClass('fe-navbar__link--active');

    if (activeKey) {
        jQuery(`.fe-navbar__link-wrapper[data-key="${activeKey}"] .fe-navbar__link`)
            .addClass('fe-navbar__link--active');
    }
});

app.vue.mount(`#${config.slug}-router`);

window.fluentEventAdmin = {};
