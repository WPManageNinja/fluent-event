import { capitalize } from 'vue';
import dayjs from 'dayjs';
import Rest from '@/utils/http/Rest.js';
import Storage from '@/utils/Storage';
import { ElNotification, ElMessageBox } from 'element-plus';

const config = window.fluentEventAdmin;

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
    data() {
        return {
            storage: Storage,
        }
    },
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
        $formatDate(date, format = 'DD-MM-YYYY') {
            return dayjs(date).format(format);
        },
        $changeTitle(title) {
            document.querySelector(
                'head title'
            ).textContent = title + ' - ' + titleCase(config.slug);
        },
        $handleError(e) {
            if (typeof e === 'string') {
                return notify('error', 'Error', e);
            }

            if (e?.isAborted) {
                return notify('warning', 'Error', e.message || 'Request aborted.');
            }

            const message = e?.message || convertToText(e?.errors, ' ');

            notify('error', 'Error', message || 'Something went wrong');
        },
        $url(url) {
            return config.rest.url.replace(
                /\/+$/, ''
            ) + '/' + url.replace(/^\/+/, '');
        },
        $notify(message, type = 'info', position = 'bottom-right') {
            const title = type === 'info' ? 'Notification' : type;
            notify(type, capitalize(title), message, position);
        },
        $notifySuccess(message, position = 'bottom-right') {
            notify('success', 'Success', message, position);
        },
        $notifyError(message, position = 'bottom-right') {
            notify('error', 'Error', message, position);
        },
        $notifyWarning(message, position = 'bottom-right') {
            notify('warning', 'Warning', message, position);
        },
        $getStatusColumnClassName({ row, column }) {
            if (column.label === 'Status') {
                return 'capitalize';
            }
        },
        $requestServer(req) {
            // destructure object
            const { method = 'get', path, payload, onSuccess, onError, onFinally, successNotify = false, fallbackSuccessMessage, errorNotify = false, fallbackErrorMessage, loadingState = 'loading', errorState = 'error' } = req;

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
                    if(errorState) {
                        this[errorState] = error?.message || this.$t('Something went wrong');
                    }
                    if (errorNotify && (error?.message || fallbackErrorMessage)) {
                        this.$handleError(error?.message || fallbackErrorMessage);
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
