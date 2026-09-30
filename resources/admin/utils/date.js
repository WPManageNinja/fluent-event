/** One day in milliseconds. */
export const DAY_MS = 86400000;

export const toUtcDay = (value) => Date.parse(`${value.slice(0, 10)}T00:00:00Z`);

/**
 * Inclusive day span between two bounds; 1 when both fall on the same date.
 * @param  {string} start
 * @param  {string} end
 * @returns {number}
 */
export const daySpan = (start, end) => (toUtcDay(end) - toUtcDay(start)) / DAY_MS + 1;
