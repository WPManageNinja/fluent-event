import { capitalize } from 'vue';
import dayjs from 'dayjs';
import Rest from '@/utils/http/Rest.js';
import { applyWeekStart } from '@/utils/date';
import { ElNotification, ElMessageBox } from 'element-plus';

const config = window.fluentEventAdmin;

applyWeekStart(config.start_of_week);

// Per-component request counters, so only the newest read writes state back.
const requestSeq = new WeakMap();

/**
 * Flatten a string / array / nested object into one string.
 * Pass a plain separator for notifications — they render text, not HTML.
 */
const convertToText = function (obj, separator = '<br />') {
    const result = [];

    if (obj === null || obj === undefined) {
        return '';
    }

    if (
        typeof obj === 'string' ||
        typeof obj === 'number' ||
        typeof obj === 'boolean'
    ) {
        result.push(String(obj));
    } else if (Array.isArray(obj)) {
        obj.forEach(item => result.push(convertToText(item, separator)));
    } else if (typeof obj === 'object') {
        Object.values(obj).forEach(
            value => result.push(convertToText(value, separator))
        );
    }

    return result.join(separator);
};

const slugify = function (text) {
    return String(text)
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^\w\-]+/g, '')
        .replace(/-{2,}/g, '-')
        .replace(/^-+|-+$/g, '');
};

const statusTypes = [
    { value: 'public', type: 'success' },
    { value: 'unlisted', type: 'warning' },
    { value: 'private', type: 'primary' },
    { value: 'draft', type: 'info' },
];

/**
 * `user_profile` → `User Profile`.
 */
const titleCase = function (str) {
    return str
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/[_\-]+/g, ' ')
        .replace(/\b\w/g, char => char.toUpperCase())
        .replace(/\s+/g, ' ')
        .trim();
};

/**
 * `ElNotification` with our offset and position defaults.
 */
const notify = function (type, title, message, position = 'bottom-right') {
    ElNotification({ type, title, message, position, offset: 20 });
}

export default {
    methods: {
        $get: Rest.get,
        $post: Rest.post,
        $put: Rest.put,
        $patch: Rest.patch,
        $del: Rest.delete,
        $alert:ElMessageBox.alert,
        $confirm:ElMessageBox.confirm,
        $capitalize: capitalize,
        $goBack() {
            this.$router.back();
        },
        $convertToText: convertToText,
        $titleCase: titleCase,
        $eventStatusType(status) {
            return statusTypes.find(item => item.value === status)?.type || 'info';
        },
        $eventStatuses() {
            return [
                { value: 'public', label: this.$t('Public'), hint: this.$t('Anyone can find it.') },
                { value: 'unlisted', label: this.$t('Unlisted'), hint: this.$t('Only accessible via direct link.') },
                { value: 'private', label: this.$t('Private'), hint: this.$t('No public page.') },
                { value: 'draft', label: this.$t('Draft'), hint: this.$t('Not ready yet.') },
            ];
        },
        $eventStatusLabel(status) {
            return this.$eventStatuses().find(item => item.value === status)?.label || status;
        },
        $entryTypes() {
            return [
                {
                    value: 'rsvp',
                    label: this.$t('RSVP'),
                    hint: this.$t('Logged-in members say whether they are going.'),
                },
                {
                    value: 'open',
                    label: this.$t('Open'),
                    hint: this.$t('Guests register with their name and email, no login needed.'),
                },
                {
                    value: 'none',
                    label: this.$t('None'),
                    hint: this.$t('Nobody registers. Anyone who can see the event sees all of it, meeting link included.'),
                },
            ];
        },
        $entryTypeLabel(type) {
            return this.$entryTypes().find(item => item.value === type)?.label || type;
        },
        $calendarStatusType(status) {
            return statusTypes.find(item => item.value === status)?.type || 'info';
        },
        // Unlisted is for events only: a calendar has no page of its own to share by link.
        $calendarStatuses() {
            return this.$eventStatuses().filter(item => item.value !== 'unlisted');
        },
        $calendarStatusLabel(status) {
            return this.$calendarStatuses().find(item => item.value === status)?.label || status;
        },
        $httpUrl(url) {
            return /^https?:/i.test(url || '') ? url : '';
        },
        $calendarColor(calendar) {
            return calendar?.meta?.theme_color || 'var(--fe-secondary-border, #9ca3af)';
        },
        $slugify: slugify,
        $download: (route) => {
            let baseUrl = '';

            try {
                new URL(route);
            } catch {
                baseUrl = config.rest.url;
            }

            if (route.startsWith('/') && baseUrl.endsWith('/')) {
                route = route.substring(1);
            }

            window.location.href = baseUrl + route;
        },
        $formatNumber(amount, hideEmpty = false) {
            if (!amount && hideEmpty) {
                return '';
            }

            if (!amount) {
                amount = '0';
            }

            return new Intl.NumberFormat('en-US').format(amount)
        },
        $formatCurrency(value, locale = 'en-US', currency = 'USD') {
            if (value == null) return '';
            
            return new Intl.NumberFormat(
                locale, { style: 'currency', currency }
            ).format(value);
        },
        $defaultDate(format = 'DD-MM-YYYY') {
            return dayjs().format(format);
        },
        $formatDate(date, format = '') {
            return dayjs(date).format(
                format || config.date_format || 'MMMM D, YYYY'
            );
        },
        $formatTime(date, format = '') {
            return dayjs(date).format(
                format || config.time_format || 'h:mm a'
            );
        },
        $formatDateTime(date) {
            return `${this.$formatDate(date)} ${this.$formatTime(date)}`;
        },
        // clock: 'local' or 'site'.
        $formatTimeRange(event, clock = 'local') {
            const start = event[`start_at_${clock}`];
            const end = event[`end_at_${clock}`];
            const zone = clock === 'site' ? config.timezone : event.timezone;

            const startText = dayjs(start).isSame(event.start_at_local, 'day') ? this.$formatTime(start) : this.$formatDateTime(start);
            const endText = dayjs(end).isSame(start, 'day') ? this.$formatTime(end) : this.$formatDateTime(end);

            return `${startText} – ${endText} (${zone})`;
        },
        $changeTitle(title) {
            document.querySelector(
                'head title'
            ).textContent = title + ' - ' + titleCase(config.slug);
        },
        $handleError(e, fallback = null) {
            const fallbackMessage = fallback || this.$t('Something went wrong');

            if (typeof e === 'string') {
                return notify('error', this.$t('Error'), e);
            }

            if (e?.isAborted) {
                return notify('warning', this.$t('Error'), e.message || this.$t('Request aborted.'));
            }

            const message = e?.message || convertToText(e?.errors, ' ') || fallbackMessage;

            notify('error', this.$t('Error'), message || fallbackMessage);
        },
        $url(url) {
            return config.rest.url.replace(
                /\/+$/, ''
            ) + '/' + url.replace(/^\/+/, '');
        },
        $notify(message, type = 'info', position = 'bottom-right') {
            const titles = {
                info: this.$t('Notification'),
                success: this.$t('Success'),
                warning: this.$t('Warning'),
                error: this.$t('Error'),
            };

            notify(type, titles[type] || capitalize(type), message, position);
        },
        $notifySuccess(message, position = 'bottom-right') {
            notify('success', this.$t('Success'), message, position);
        },
        $notifyError(message, position = 'bottom-right') {
            notify('error', this.$t('Error'), message, position);
        },
        $notifyWarning(message, position = 'bottom-right') {
            notify('warning', this.$t('Warning'), message, position);
        },
        $getStatusColumnClassName({ row, column }) {
            if (column.label === 'Status') {
                return 'capitalize';
            }
        },
        $requestServer(req) {
            // destructure object
            const { method = 'get', path, payload, onSuccess, onError, onFinally, successNotify = false, fallbackSuccessMessage, errorNotify = false, fallbackErrorMessage, loadingState = 'loading', errorState = 'error', notFoundPage = false } = req;

            // state safety check if available
            if ((loadingState && this[loadingState] === undefined) || 
                (errorState && this[errorState] === undefined)) {
                throw new Error(
                    `$requestServer: unknown state for "${loadingState}" or "${errorState}" — declare it in data().`
                );
            }

            // Mutations keep the double-submit guard. Reads never do - a new
            // filter or page picked mid-flight has to reach the server.
            if (loadingState) {
                if (method !== 'get' && this[loadingState]) return;
                this[loadingState] = true;
            }

            // Newest request wins, so a slow earlier read cannot repaint over it.
            // Added this counter due to repeatative review requests by @autharif
            const key = loadingState || path;
            let counters = requestSeq.get(this);
            if (!counters) requestSeq.set(this, counters = {});
            const seq = counters[key] = (counters[key] || 0) + 1;
            const isLatest = () => counters[key] === seq;

            Rest[method](path, payload)
                .then(response => {
                    if (!isLatest()) return;
                    if (errorState) this[errorState] = '';

                    if (successNotify && (response?.message || fallbackSuccessMessage)) {
                        this.$notifySuccess(response?.message || fallbackSuccessMessage);
                    }
                    onSuccess?.(response);
                })
                .catch(error => {
                    if (!isLatest()) return;
                    // A record that is gone gets the 404 page at its own address; retrying can't bring it back.
                    if (notFoundPage && error?.status === 404) {
                        this.$router.replace({ name: 'notfound', params: { pathMatch: this.$route.path.slice(1).split('/') }, query: this.$route.query });
                        return;
                    }
                    if(errorState) {
                        this[errorState] = error?.message || this.$t('Something went wrong');
                    }
                    if (errorNotify) {
                        this.$handleError(error, fallbackErrorMessage);
                    }
                    onError?.(error);
                })
                .finally(() => {
                    if (!isLatest()) return;
                    if (loadingState) this[loadingState] = false;
                    onFinally?.();
                });
        }
    }
};
