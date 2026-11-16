import { describe, it, expect } from 'vitest';
import { compile } from 'sass';

// FluentCommunity's default skin (fluent-community/app/Functions/Utility.php), light and dark.
const SKINS = {
    light: { '--fcom-primary-text': '#19283a', '--fcom-secondary-text': '#525866', '--fcom-active-bg': '#f0f3f5' },
    dark: { '--fcom-primary-text': '#F0F3F5', '--fcom-secondary-text': '#99A0AE', '--fcom-active-bg': '#42464D' },
};

const hex = (value) => [1, 3, 5].map((i) => parseInt(value.slice(i, i + 2), 16));

// A colour from the stylesheet, with var() read from the skin and color-mix() mixed in sRGB.
function resolve(value, skin) {
    const variable = value.match(/^var\((--[\w-]+)/);

    if (variable) {
        return hex(skin[variable[1]]);
    }

    const mix = value.match(/^color-mix\(in srgb,\s*(var\([^)]*\))\s*(\d+)%,\s*(var\([^)]*\))\)$/);

    if (mix) {
        const [a, b, share] = [resolve(mix[1], skin), resolve(mix[3], skin), mix[2] / 100];

        return a.map((channel, i) => channel * share + b[i] * (1 - share));
    }

    throw new Error(`Unhandled colour: ${value}`);
}

function contrast(a, b) {
    const luminance = (rgb) => {
        const [r, g, bl] = rgb.map((v) => {
            const c = v / 255;

            return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
        });

        return 0.2126 * r + 0.7152 * g + 0.0722 * bl;
    };
    const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (light + 0.05) / (dark + 0.05);
}

// The last value the rule gives the property, the one the browser keeps.
function declared(css, selector, property) {
    const rule = css.match(new RegExp(`${selector.replace(/\./g, '\\.')}\\s*\\{([^}]*)\\}`))[1];

    return [...rule.matchAll(new RegExp(`(?:^|;)\\s*${property}:\\s*([^;]+)`, 'g'))].map((m) => m[1].trim()).at(-1);
}

describe('community countdown labels', () => {
    const css = compile('resources/fcom-portal/fcom-portal.scss').css;

    // The event page's countdown, and the event form help's drawing of it.
    const blocks = ['.fe-countdown', '.fe-start-preview'];

    // At 9px the labels need 4.5:1; the default dark skin's secondary text on a tile was 3.61:1.
    it.each(blocks.flatMap((block) => Object.keys(SKINS).map((name) => [block, name])))('%s labels read at 4.5:1 or more on a tile in the default %s skin', (block, name) => {
        const label = resolve(declared(css, `${block}__label`, 'color'), SKINS[name]);
        const tile = resolve(declared(css, `${block}__unit`, 'background'), SKINS[name]);

        expect(contrast(label, tile)).toBeGreaterThanOrEqual(4.5);
    });
});
