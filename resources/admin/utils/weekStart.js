import dayjs from 'dayjs';
import updateLocale from 'dayjs/plugin/updateLocale';

dayjs.extend(updateLocale);

// WordPress "Week Starts On" for our dayjs (Element Plus reads it) and, in the portal, fcom's window.dayjs.
export function applyWeekStart(value, hostDayjs) {
    const day = parseInt(value, 10);
    const weekStart = day >= 0 && day <= 6 ? day : 0;

    dayjs.updateLocale('en', { weekStart });
    hostDayjs?.updateLocale('en', { weekStart });
}
