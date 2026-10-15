import { describe, it, expect, afterEach } from 'vitest';
import cookie from '@/utils/Cookie';
import { expectKnownIssue } from '../known-issue';

const encode = (value) => btoa(JSON.stringify(value));

describe('Cookie', () => {
    afterEach(() => {
        document.cookie = 'fe_state=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    });

    it('reads a base64 JSON cookie by name', () => {
        const header = `other=1; fe_state=${encode({ view: 'calendar' })}`;

        expect(cookie.get('fe_state', header)).toEqual({ view: 'calendar' });
    });

    it('reads a value whose base64 padding ends in "="', () => {
        // parse() drops the padding when it splits on "="; atob still decodes the rest.
        const value = encode({ ok: true });
        expect(value.endsWith('=')).toBe(true);

        expect(cookie.get('fe_state', `fe_state=${value}`)).toEqual({ ok: true });
    });

    it('returns undefined for a cookie that is not set', () => {
        expect(cookie.get('fe_state', 'other=1')).toBeUndefined();
    });

    it('decodes URL-encoded names and values', () => {
        expect(cookie.parse('fe%20name=hello%20world')).toEqual({ 'fe name': 'hello world' });
    });

    it('falls back to document.cookie when no header is passed', () => {
        document.cookie = `fe_state=${encode({ tab: 'past' })}`;

        expect(cookie.get('fe_state')).toEqual({ tab: 'past' });
    });

    it('KNOWN ISSUE #174 — keeps a value that itself contains "="', async (context) => {
        // #174: parse() splits on every "=", so WordPress's own wp-settings-1 cookie is cut short.
        await expectKnownIssue('#174', context, () => {
            expect(cookie.parse('wp-settings-1=libraryContent=browse&editor=tinymce'))
                .toEqual({ 'wp-settings-1': 'libraryContent=browse&editor=tinymce' });
        });
    });

    it('KNOWN ISSUE #174 — reads a cookie with no "=" as an empty value', async (context) => {
        // #174: a valueless cookie (legal per RFC 6265) makes parse() throw.
        await expectKnownIssue('#174', context, () => {
            expect(cookie.parse('fe_flag; fe_state=abc')).toEqual({ fe_flag: '', fe_state: 'abc' });
        });
    });
});
