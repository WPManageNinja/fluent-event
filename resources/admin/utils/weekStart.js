import dayjs from 'dayjs';
import updateLocale from 'dayjs/plugin/updateLocale';

dayjs.extend(updateLocale);

// WordPress "Week Starts On" for our dayjs (Element Plus calendars and pickers read it) and another copy the page uses.
export function applyWeekStart(value, otherDayjs) {
    const weekStart = parseInt(value, 10) || 0;

    dayjs.updateLocale('en', { weekStart });
    otherDayjs?.updateLocale('en', { weekStart });
}
