import { describe, it, expect, vi } from 'vitest';
import dayjs from 'dayjs';
import { applyWeekStart } from '@/utils/weekStart';

// main.js reads the setting once at import; this site starts its week on Monday.
window.fluentEventAdmin.start_of_week = '1';
await import('@/mixins/main');

describe('admin week start', () => {
    it('starts weeks on the WordPress "Week Starts On" day', () => {
        // 2026-10-28 is a Wednesday.
        expect(dayjs('2026-10-28').startOf('week').format('YYYY-MM-DD')).toBe('2026-10-26');
        expect(dayjs('2026-10-28').endOf('week').format('YYYY-MM-DD')).toBe('2026-11-01');
    });

    it('stores the localized string as a number', () => {
        // A string weekStart makes Element Plus build a 13-column calendar header.
        expect(dayjs.Ls.en.weekStart).toBe(1);
    });
});

describe('applyWeekStart', () => {
    it('gives every other dayjs copy the same day', () => {
        const other = { updateLocale: vi.fn() };

        applyWeekStart('6', other);

        expect(other.updateLocale).toHaveBeenCalledWith('en', { weekStart: 6 });
        expect(dayjs.Ls.en.weekStart).toBe(6);
    });

    it('falls back to Sunday and skips a missing copy', () => {
        expect(() => applyWeekStart(undefined, undefined)).not.toThrow();
        expect(dayjs.Ls.en.weekStart).toBe(0);
    });
});
