import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Application from '@/bootstrap/Application';
import { useTranslation } from '@/composables/useTranslation';
import { translate, translatePlural } from '../../setup';
import { expectKnownIssue } from '../known-issue';

// Application reads fluentEventAdmin.i18n and user_locale once, when the globals are registered.
const boot = (vars = {}) => {
    Object.assign(window.fluentEventAdmin, vars);
    const app = { config: { globalProperties: {} } };
    new Application().registerGlobalProperties(app);

    return app.config.globalProperties;
};

describe('$t', () => {
    it('returns the English key when no translation is registered', () => {
        expect(boot().$t('Create Event')).toBe('Create Event');
    });

    it('returns the registered translation', () => {
        expect(boot({ i18n: { 'Create Event': 'Veranstaltung erstellen' } }).$t('Create Event'))
            .toBe('Veranstaltung erstellen');
    });

    it('fills %s and %d placeholders in order', () => {
        expect(boot().$t('%s has %d guests', 'Meetup', 12)).toBe('Meetup has 12 guests');
    });

    it('lets a translation reorder arguments with positional placeholders', () => {
        const { $t } = boot({ i18n: { '%1$s – %2$s': '%2$s ← %1$s' } });

        expect($t('%1$s – %2$s', 'start', 'end')).toBe('end ← start');
    });

    it('renders a missing argument as nothing rather than a raw placeholder', () => {
        expect(boot().$t('%s going', undefined)).toBe(' going');
    });
});

describe('$_n', () => {
    it('picks the singular form for exactly one and fills the count', () => {
        expect(boot().$_n('%d guest', '%d guests', 1)).toBe('1 guest');
    });

    it('picks the plural form for zero and many', () => {
        const { $_n } = boot();

        expect($_n('%d guest', '%d guests', 0)).toBe('0 guests');
        expect($_n('%d guest', '%d guests', 5)).toBe('5 guests');
    });

    it('treats a numeric string count like a number', () => {
        // REST counts arrive as strings from some MySQL drivers.
        expect(boot().$_n('%d event', '%d events', '1')).toBe('1 event');
    });

    it('translates whichever form it picks', () => {
        const { $_n } = boot({ i18n: { '%d guests': '%d Gäste' } });

        expect($_n('%d guest', '%d guests', 3)).toBe('3 Gäste');
    });
});

describe('$n', () => {
    it('formats numbers for the WordPress user locale', () => {
        expect(boot({ user_locale: 'de_DE' }).$n(1234.5)).toBe('1.234,5');
    });

    it('passes a non-number through as a string', () => {
        expect(boot().$n('12')).toBe('12');
        expect(boot().$n(Infinity)).toBe('Infinity');
    });

    it('formats numbers for a three-part WordPress locale', async () => {
        // #175: only the first "_" becomes "-", so de_DE_formal is an invalid tag and $n returns raw digits.
        await expectKnownIssue('#175', () => {
            expect(boot({ user_locale: 'de_DE_formal' }).$n(1234.5)).toBe(new Intl.NumberFormat('de-DE').format(1234.5));
        });
    });
});

describe('test setup translation stub', () => {
    const cases = [
        ['Create Event'],
        ['%s has %d guests', 'Meetup', 12],
        ['%1$s – %2$s', 'start', 'end'],
        ['%s going', undefined],
        ['Translated %s', 'value']
    ];

    beforeEach(() => {
        window.fluentEventAdmin.i18n = { 'Translated %s': 'Übersetzt %s', '%1$s – %2$s': '%2$s ← %1$s' };
    });

    it('translates exactly like the real $t', () => {
        const { $t } = boot();

        cases.forEach(([key, ...args]) => expect(translate(key, ...args)).toBe($t(key, ...args)));
    });

    it('pluralises exactly like the real $_n', () => {
        const { $_n } = boot();

        [0, 1, '1', 2].forEach(count => {
            expect(translatePlural('%d guest', '%d guests', count)).toBe($_n('%d guest', '%d guests', count));
        });
    });
});

describe('useTranslation', () => {
    it("hands a component the app's own $t and $n", () => {
        const $n = (value) => `#${value}`;
        let helpers = null;

        mount({
            template: '<div></div>',
            created() {
                helpers = useTranslation();
            }
        }, { global: { mocks: { $n } } });

        expect(helpers.$t('%d guests', 3)).toBe('3 guests');
        expect(helpers.$n(4)).toBe('#4');
    });
});
