// The countdown to an event's start, shared by the community event page (Vue) and the public one (portal.js).

const DAY = 86400;

// Days hold everything above a day, so six weeks read 42 days. Past the start every part is 0, never negative.
export function countdownParts(startMs, nowMs = Date.now()) {
    const left = Math.max(0, Math.floor((startMs - nowMs) / 1000));

    return {
        days: Math.floor(left / DAY),
        hours: Math.floor((left % DAY) / 3600),
        minutes: Math.floor((left % 3600) / 60),
        seconds: left % 60,
        done: left === 0,
    };
}

export function padPart(value) {
    return String(value).padStart(2, '0');
}

// Calls onTick now and every second, read from the clock each time so a throttled tab catches up, and stops
// once it reaches 0. Returns the stop, for a page that goes away first.
export function startCountdown(startMs, onTick) {
    const timer = setInterval(tick, 1000);

    function tick() {
        const parts = countdownParts(startMs);

        onTick(parts);

        if (parts.done) {
            clearInterval(timer);
        }
    }

    tick();

    return () => clearInterval(timer);
}
