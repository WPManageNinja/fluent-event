import { beforeEach } from 'vitest';
import { config, RouterLinkStub } from '@vue/test-utils';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import relativeTime from 'dayjs/plugin/relativeTime';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import updateLocale from 'dayjs/plugin/updateLocale';
import customParseFormat from 'dayjs/plugin/customParseFormat';
import advancedFormat from 'dayjs/plugin/advancedFormat';

// Shapes mirror AdminMenuHandler::localizeScript(), PortalHandler::printVars() and fcom's portal vars.
export const adminVars = () => ({
    env: 'dev',
    slug: 'fluent-event',
    theme: null,
    user_locale: 'en_US',
    timezone: 'UTC',
    // wp_localize_script() stringifies scalars.
    start_of_week: '0',
    general_settings: {
        default_events_view: 'timeline',
        disable_event_view_switch: 'no'
    },
    email_notifications: {
        registration_confirmation: { enabled: true },
        registration_cancellation: { enabled: true },
        reminder_to_guest: { enabled: true, times: [{ value: 24, unit: 'hours' }] }
    },
    date_format: 'MMM D, YYYY',
    time_format: 'h:mm a',
    brand_logo: '',
    nonce: 'test-ajax-nonce',
    asset_url: 'https://example.test/wp-content/plugins/fluent-events/assets/',
    rest: {
        base_url: 'https://example.test/wp-json/',
        url: 'https://example.test/wp-json/fluent-event/v2',
        nonce: 'test-rest-nonce',
        namespace: 'fluent-event',
        version: 'v2'
    },
    baseUrl: 'https://example.test/wp-admin/admin.php?page=fluent-event#/',
    name: 'FluentEvent',
    hook_prefix: 'fluent-event',
    logoUrl: 'https://example.test/wp-content/plugins/fluent-events/assets/images/logo.svg',
    me: {
        id: 1,
        email: 'admin@example.test',
        full_name: 'Site Admin',
        avatar: '',
        is_admin: true,
        permissions: ['manage_all_data']
    },
    i18n: {}
});

export const portalVars = () => ({
    rest_url: 'https://example.test/wp-json/fluent-event/v2',
    is_logged_in: true,
    can_create_events: true,
    default_view: 'timeline',
    disable_view_switch: false,
    start_of_week: 0,
    i18n: {}
});

export const fcomVars = () => ({
    rest: {
        url: 'https://example.test/wp-json/fluent-community/v2',
        nonce: 'test-fcom-nonce'
    },
    date_formatter: 'MMMM D, YYYY',
    time_formatter: 'h:mm A',
    auth_url: 'https://example.test/portal/?fcom_action=auth',
    portal_url: 'https://example.test/portal/'
});

// main.js and CreateOrUpdateEvent.vue capture this object at import, so it is reset in place, never replaced.
const reset = (target, values) => {
    Object.keys(target).forEach(key => delete target[key]);
    return Object.assign(target, values);
};

window.fluentEventAdmin = adminVars();
window.fluentEventFcomPortal = portalVars();
window.fluentComAdmin = fcomVars();

// fcom's portal publishes its dayjs, extended as its start.js does; the portal code calls window.dayjs.
[utc, timezone, relativeTime, localizedFormat, updateLocale, customParseFormat, advancedFormat]
    .forEach(plugin => dayjs.extend(plugin));
window.dayjs = dayjs;

beforeEach(() => {
    reset(window.fluentEventAdmin, adminVars());
    window.fluentEventFcomPortal = reset(window.fluentEventFcomPortal || {}, portalVars());
    window.fluentComAdmin = reset(window.fluentComAdmin || {}, fcomVars());
    localStorage.clear();
});

// The real $t lives in Application.js and needs the whole SPA; translation.spec.js keeps this copy in step.
export const translate = (key, ...args) => {
    const text = (window.fluentEventAdmin.i18n || {})[key] || key;

    if (!args.length) {
        return text;
    }

    let index = 0;

    return text.replace(/%(?:(\d+)\$)?[sd]/g, (match, position) => {
        const arg = position ? args[position - 1] : args[index++];

        return arg === undefined ? '' : arg;
    });
};

export const translatePlural = (single, plural, count) => translate(Number(count) === 1 ? single : plural, count);

// Element Plus is stubbed by default; a spec that needs a real component overrides `global.stubs`.
config.global.stubs = {
    Teleport: true,
    RouterLink: RouterLinkStub,
    ElButton: true,
    ElButtonGroup: true,
    ElIcon: true,
    ElInput: true,
    ElForm: true,
    ElFormItem: true,
    ElSelect: true,
    ElSelectV2: true,
    ElOption: true,
    ElRadio: true,
    ElRadioGroup: true,
    ElCheckbox: true,
    ElSwitch: true,
    ElTag: true,
    ElDialog: true,
    ElDrawer: true,
    ElPopover: true,
    ElTooltip: true,
    ElDatePicker: true,
    ElTimePicker: true,
    ElSkeleton: true,
    ElSkeletonItem: true,
    ElEmpty: true,
    ElPagination: true,
    ElCalendar: true,
    ElTabs: true,
    ElTabPane: true
};

config.global.mocks = {
    $t: translate,
    $_n: translatePlural,
    appVars: window.fluentEventAdmin
};
