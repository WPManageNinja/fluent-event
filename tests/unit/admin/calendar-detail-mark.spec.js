import { describe, it, expect } from 'vitest';
import CalendarDetail from '@/modules/calendars/detail.vue';

const ink = (theme_color) => CalendarDetail.computed.markStyle.call({ calendar: { meta: { theme_color } } }).color;

// The initials take whichever ink reads better on the calendar colour.
describe('Calendar detail mark', () => {
    it('writes dark on mid-bright hues and white on deep ones', () => {
        expect(ink('#0099ff')).toContain('--fe-on-hue-dark');
        expect(ink('#ff4f00')).toContain('--fe-on-hue-dark');
        expect(ink('#ccf000')).toContain('--fe-on-hue-dark');
        expect(ink('#8247f5')).toContain('--fe-on-hue-light');
        expect(ink('#253241')).toContain('--fe-on-hue-light');
    });
});
