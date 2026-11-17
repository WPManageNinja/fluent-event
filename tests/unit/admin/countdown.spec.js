import { describe, it, expect, vi, afterEach } from 'vitest';
import { countdownParts, padPart, startCountdown } from '@/utils/countdown';

const SECOND = 1000;
const HOUR = 3600 * SECOND;
const DAY = 24 * HOUR;

describe('countdown to the start', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('keeps everything above a day in days, never months', () => {
        const left = 45 * DAY + 20 * HOUR + 4 * 60 * SECOND + 40 * SECOND;

        expect(countdownParts(left, 0)).toEqual({ days: 45, hours: 20, minutes: 4, seconds: 40, done: false });
        expect(padPart(4)).toBe('04');
        expect(padPart(45)).toBe('45');
    });

    it('stops at 0, never below, once the start has passed', () => {
        expect(countdownParts(5 * SECOND, 5 * SECOND)).toEqual({ days: 0, hours: 0, minutes: 0, seconds: 0, done: true });
        expect(countdownParts(5 * SECOND, 60 * SECOND)).toEqual({ days: 0, hours: 0, minutes: 0, seconds: 0, done: true });
    });

    it('ticks every second and stops after the tick that reaches 0', () => {
        vi.useFakeTimers();
        vi.setSystemTime(0);
        const ticks = [];

        startCountdown(2 * SECOND, (parts) => ticks.push(parts.seconds));
        vi.advanceTimersByTime(10 * SECOND);

        expect(ticks).toEqual([2, 1, 0]);
    });
});
