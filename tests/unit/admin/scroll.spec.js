import { describe, it, expect, beforeEach } from 'vitest';
import { containWheel } from '@/utils/scroll';

const box = (scrollHeight, clientHeight, scrollTop = 0) => {
    const el = document.createElement('div');
    el.style.overflowY = 'auto';
    Object.defineProperty(el, 'scrollHeight', { value: scrollHeight });
    Object.defineProperty(el, 'clientHeight', { value: clientHeight });
    el.scrollTop = scrollTop;
    return el;
};

const wheel = (target, deltaY) => {
    const event = new WheelEvent('wheel', { deltaY, bubbles: true, cancelable: true });
    target.dispatchEvent(event);
    return event.defaultPrevented;
};

describe('containWheel', () => {
    let app;
    let content;

    beforeEach(() => {
        document.body.replaceChildren();
        content = document.createElement('p');
    });

    const mount = (root) => {
        app = root;
        app.appendChild(content);
        document.body.appendChild(app);
        containWheel(app);
    };

    it('keeps the page still when the app has nothing to scroll', () => {
        mount(box(500, 500));

        expect(wheel(content, 120)).toBe(true);
        expect(wheel(content, -120)).toBe(true);
    });

    it('lets the app scroll until it reaches its end, then holds the page', () => {
        mount(box(1500, 500, 0));

        expect(wheel(content, 120)).toBe(false);
        expect(wheel(content, -120)).toBe(true);

        app.scrollTop = 1000;
        expect(wheel(content, 120)).toBe(true);
        expect(wheel(content, -120)).toBe(false);
    });

    it('leaves a scrollable panel inside a short app alone', () => {
        mount(box(500, 500));
        const panel = box(800, 200);
        const row = document.createElement('span');
        panel.appendChild(row);
        app.appendChild(panel);

        expect(wheel(row, 120)).toBe(false);
    });
});
