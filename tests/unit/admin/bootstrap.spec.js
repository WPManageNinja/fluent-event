import { describe, it, expect } from 'vitest';
import { createApp } from 'vue';
import Application from '@/bootstrap/Application';
import config from '@/bootstrap/config';
import { useConfig } from '@/composables/useConfig';
import { toAlpha } from '@/composables/useCssVar';
import ResponseProxyItr from '@/utils/http/ResponseProxyItr';
import { expectKnownIssue } from '../known-issue';

describe('config', () => {
    const seed = () => Object.assign(config, {
        slug: 'fluent-event',
        rest: { url: 'https://example.test/wp-json/fluent-event/v2', nonce: 'abc' },
        me: { permissions: [] },
        theme: null
    });

    it('reads a nested value by dotted key', () => {
        seed();

        expect(config.get('rest.nonce')).toBe('abc');
    });

    it('returns the default for a missing or null path', () => {
        seed();

        expect(config.get('rest.missing', 'fallback')).toBe('fallback');
        expect(config.get('nope.deeper')).toBeNull();
        expect(config.get('theme', 'light')).toBe('light');
    });

    it('keeps a falsy value that is actually set', () => {
        seed();

        expect(config.get('me.permissions', ['x'])).toEqual([]);
    });

    it('returns a copy of everything when no key is given', () => {
        seed();
        const all = config.get();
        all.slug = 'changed';

        expect(config.slug).toBe('fluent-event');
    });

    it('exposes the same reader through useConfig', () => {
        seed();
        const { config: exposed, getConfig } = useConfig();

        expect(exposed).toBe(config);
        expect(getConfig('rest.url')).toBe('https://example.test/wp-json/fluent-event/v2');
    });
});

describe('validation error plugin', () => {
    const install = () => {
        const app = createApp({});
        new Application().registerValidationErrorHandler(app);

        return app.config.globalProperties;
    };

    it('collects field errors from a rejected REST response', () => {
        const gp = install();
        const response = new ResponseProxyItr({
            status: 422,
            responseJSON: { title: { required: 'Event title is required.' } }
        });

        gp.$handleValidationError(response);

        expect(gp.$validationErrors).toEqual({ title: { required: 'Event title is required.' } });
    });

    it('replaces errors from an earlier submit instead of merging them', () => {
        const gp = install();

        gp.$handleValidationError({ errors: { title: { required: 'Required' } } });
        gp.$handleValidationError({ errors: { end_at: { after: 'End must be after the start.' } } });

        expect(Object.keys(gp.$validationErrors)).toEqual(['end_at']);
    });

    it('clears every error on demand', () => {
        const gp = install();
        gp.$handleValidationError({ errors: { title: { required: 'Required' } } });

        gp.$clearErrors();

        expect(gp.$validationErrors).toEqual({});
    });
});

describe('toAlpha', () => {
    it('turns a six-digit hex colour into rgba', () => {
        expect(toAlpha('#19283a', 0.5)).toBe('rgba(25,40,58, 0.5)');
    });

    it('defaults to 20% opacity', () => {
        expect(toAlpha('#ffffff')).toBe('rgba(255,255,255, 0.2)');
    });

    it('returns an empty string for a missing colour', () => {
        expect(toAlpha('')).toBe('');
        expect(toAlpha(null)).toBe('');
    });

    it('accepts three-digit hex shorthand', async () => {
        // #176: the docblock promises #RGB support, but "fff" splits into ["ff", "f"] and is rejected.
        await expectKnownIssue('#176', () => {
            expect(toAlpha('#fff')).toBe('rgba(255,255,255, 0.2)');
        });
    });
});
