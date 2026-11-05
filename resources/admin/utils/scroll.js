const canScroll = (el, deltaY) => {
    if (!/(auto|scroll)/.test(getComputedStyle(el).overflowY) || el.scrollHeight <= el.clientHeight) {
        return false;
    }

    return deltaY > 0 ? el.scrollTop + el.clientHeight < el.scrollHeight - 1 : el.scrollTop > 0;
};

// A long wp-admin menu makes the page taller than the window, so a wheel over a short app page would scroll
// the whole page. Swallow it unless the app, or a panel inside it, can still scroll that way.
export const containWheel = (app) => {
    app.addEventListener('wheel', (event) => {
        for (let el = event.target; el && el !== app.parentElement; el = el.parentElement) {
            if (canScroll(el, event.deltaY)) {
                return;
            }
        }

        event.preventDefault();
    }, { passive: false });
};
