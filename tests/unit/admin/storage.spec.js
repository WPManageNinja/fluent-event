import { describe, it, expect, vi, afterEach } from 'vitest';
import Storage from '@/utils/Storage';

describe('Storage', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('namespaces its keys so it cannot collide with another plugin', () => {
        Storage.set('events_view', 'calendar');

        expect(localStorage.getItem('fevent_events_view')).toBe('"calendar"');
        expect(localStorage.getItem('events_view')).toBeNull();
    });

    it('round trips objects and arrays with their types intact', () => {
        Storage.set('filters', { status: 'public', page: 2, archived: false });
        Storage.set('columns', ['title', 'start_at']);

        expect(Storage.get('filters')).toEqual({ status: 'public', page: 2, archived: false });
        expect(Storage.get('columns')).toEqual(['title', 'start_at']);
    });

    it('returns the default for a key that was never set', () => {
        expect(Storage.get('missing')).toBeNull();
        expect(Storage.get('missing', 'timeline')).toBe('timeline');
    });

    it('returns a stored falsy value instead of the default', () => {
        Storage.set('count', 0);
        Storage.set('enabled', false);

        expect(Storage.get('count', 5)).toBe(0);
        expect(Storage.get('enabled', true)).toBe(false);
    });

    it('returns the default when the stored value is not JSON', () => {
        // A value written by hand or by an older build must not break the page that reads it.
        localStorage.setItem('fevent_events_view', 'calendar');

        expect(Storage.get('events_view', 'timeline')).toBe('timeline');
    });

    it('returns the default when storage is blocked', () => {
        vi.spyOn(localStorage, 'getItem').mockImplementation(() => {
            throw new DOMException('Access denied', 'SecurityError');
        });

        expect(Storage.get('events_view', 'timeline')).toBe('timeline');
    });

    it('forgets the preference quietly when storage is full', () => {
        vi.spyOn(localStorage, 'setItem').mockImplementation(() => {
            throw new DOMException('Quota exceeded', 'QuotaExceededError');
        });

        expect(() => Storage.set('events_view', 'cards')).not.toThrow();
    });
});
