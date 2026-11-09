import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { ElNotification } from 'element-plus';
import { t } from '../../../resources/fcom-portal/utils';
import CreateEventPage from '../../../resources/fcom-portal/app/CreateEventPage.vue';

vi.mock('element-plus', async (importOriginal) => ({ ...(await importOriginal()), ElNotification: vi.fn() }));

const json = (body, status = 200) => new Response(JSON.stringify(body), { status });
const insertContent = vi.fn();

// v-loading is never registered in the portal app (see events-page.spec.js); drop only that warning.
const warnHandler = (message, instance, trace) => {
    if (!message.includes('Failed to resolve directive: loading')) {
        console.warn(`[Vue warn]: ${message}${trace}`);
    }
};

// The editor-assets load is cached in module scope; a fresh copy of the page shows it run.
const freshPage = async () => {
    vi.resetModules();
    return (await import('../../../resources/fcom-portal/app/CreateEventPage.vue')).default;
};

const createPage = async (Page = CreateEventPage, extraProps = {}, extraStubs = {}) => {
    const props = { router: { push: vi.fn(), back: vi.fn() }, onChange: vi.fn(), submitPost: vi.fn(), onPlacement: vi.fn(), ...extraProps };
    const wrapper = mount(Page, {
        props,
        global: {
            mocks: { $t: t },
            stubs: {
                ElFormItem: { template: '<div><slot /></div>' },
                ElInput: { template: '<input />', methods: { focus() {} } },
                WpEditor: { template: '<div></div>', methods: { insertContent } },
                ...extraStubs
            },
            config: { warnHandler }
        }
    });
    await flushPromises();

    return { wrapper, vm: wrapper.vm, props };
};

const pickFile = (vm, handler) => vm[handler]({ target: { files: [new File(['x'], 'cover.png')], value: 'C:\\cover.png' } });

beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn(async () => json({ html: '' })));
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-14T09:20:00Z'));
    insertContent.mockClear();
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('CreateEventPage', () => {
    describe('where to post', () => {
        // The page's first section, so the first radio group; Element Plus is stubbed (tests/setup.js).
        const placementGroup = (wrapper) => wrapper.findAllComponents({ name: 'ElRadioGroup' })[0];
        const radio = (wrapper, value) => wrapper.findAllComponents({ name: 'ElRadio' }).find(r => r.props('value') === value);

        it('starts on In a space and reports a change to the shell', async () => {
            const { wrapper, props } = await createPage();

            expect(placementGroup(wrapper).props('modelValue')).toBe('space');

            placementGroup(wrapper).vm.$emit('change', 'global');

            expect(props.onPlacement).toHaveBeenCalledWith('global');
        });

        // The group and the tooltip render their slots so the radios inside can be read.
        const slotStubs = {
            ElRadioGroup: { name: 'ElRadioGroup', template: '<div><slot /></div>', props: ['modelValue'] },
            ElTooltip: { name: 'ElTooltip', template: '<div><slot /></div>', props: ['content', 'disabled'] },
        };

        it("disables Community-wide, with a tooltip on where to turn it on, when fcom's global posts are off", async () => {
            const { wrapper } = await createPage(CreateEventPage, { globalPosts: false }, slotStubs);
            const tooltip = wrapper.findAllComponents({ name: 'ElTooltip' })[0];

            expect(placementGroup(wrapper).props('modelValue')).toBe('space');
            expect(radio(wrapper, 'global').props('disabled')).toBe(true);
            expect(radio(wrapper, 'space').props('disabled')).toBeFalsy();
            expect(tooltip.props('disabled')).toBe(false);
            expect(tooltip.props('content')).toContain('Global Post Status');
        });

        it('keeps the tooltip off while Community-wide can be picked', async () => {
            const { wrapper } = await createPage(CreateEventPage, {}, slotStubs);

            expect(wrapper.findAllComponents({ name: 'ElTooltip' })[0].props('disabled')).toBe(true);
        });
    });

    it('hands the composer a full event as soon as it opens', async () => {
        const { props } = await createPage();

        expect(props.onChange).toHaveBeenCalledWith(expect.objectContaining({
            start_at: '2026-10-14 10:00:00',
            end_at: '2026-10-14 11:00:00',
            venue_type: 'offline',
            entry_type: 'rsvp',
            show_guest_count: 'yes'
        }));
    });

    it('hands the composer every later change', async () => {
        const { vm, props } = await createPage();

        vm.event.title = 'Design review';
        await vm.$nextTick();

        expect(props.onChange.mock.calls.at(-1)[0].title).toBe('Design review');
    });

    it("shows both date pickers in fcom's date format", async () => {
        window.fluentComAdmin.date_formatter = 'DD/MM/YYYY';
        const { wrapper } = await createPage();

        const formats = wrapper.findAllComponents({ name: 'ElDatePicker' }).map(picker => picker.props('format'));

        expect(formats).toEqual(['DD/MM/YYYY', 'DD/MM/YYYY']);
    });

    it('pulls the end date forward when the start moves past it', async () => {
        const { vm } = await createPage();
        vm.event.start_date = '2026-10-20';

        vm.syncEndDate();

        expect(vm.event.end_date).toBe('2026-10-20');
    });

    it("submits through fcom's composer and mirrors its posting state on the button", async () => {
        const { vm, props } = await createPage();
        vm.event.title = 'Friday Q&A';

        vm.submit();
        const onPosting = props.submitPost.mock.calls[0][0];

        onPosting(true);
        expect(vm.saving).toBe(true);
        onPosting(false);
        expect(vm.saving).toBe(false);
    });

    // Opening fcom's composer would move the focus to its title, so a bad event only shows its error.
    it('shows an invalid event as a toast and leaves the composer alone', async () => {
        const { vm, props } = await createPage();
        const refused = (message) => {
            ElNotification.mockClear();
            vm.submit();
            expect(ElNotification).toHaveBeenCalledWith({ type: 'error', title: 'Error', message });
        };

        vm.event.title = '  ';
        refused('Event title is required.');

        vm.event.title = 'Friday Q&A';
        vm.event.end_date = vm.event.start_date;
        vm.event.end_time = vm.event.start_time;
        refused('End must be after the start.');

        vm.event.end_date = '2026-12-31';
        vm.event.venue_type = 'online';
        refused('An online event needs a meeting link.');

        expect(props.submitPost).not.toHaveBeenCalled();
    });

    it('fetches the WordPress editor assets once, not on every visit', async () => {
        const Page = await freshPage();
        await createPage(Page);
        await createPage(Page);

        const loads = fetch.mock.calls.filter(([url]) => url.endsWith('/community/editor-assets'));
        expect(loads.map(([url]) => url)).toEqual(['https://example.test/wp-json/fluent-event/v2/community/editor-assets']);
    });

    it('opens the editor even when its assets fail to load', async () => {
        fetch.mockResolvedValueOnce(json({ message: 'Forbidden' }, 403));

        const { vm } = await createPage(await freshPage());

        expect(vm.editorReady).toBe(true);
    });

    it('adds editor markup once, leaving elements already on the page alone', async () => {
        const existing = document.createElement('div');
        existing.id = 'wp-link-backdrop';
        document.body.appendChild(existing);
        const { vm } = await createPage();

        await vm.insertMarkup('<div id="wp-link-backdrop"></div><div id="wp-link-wrap"></div>');

        expect(document.querySelectorAll('#wp-link-backdrop')).toHaveLength(1);
        expect(document.querySelectorAll('#wp-link-wrap')).toHaveLength(1);
        existing.remove();
        document.getElementById('wp-link-wrap').remove();
    });

    it('uploads a cover through fcom and keeps its URL', async () => {
        const { vm } = await createPage();
        fetch.mockResolvedValueOnce(json({ media: { url: 'https://example.test/uploads/cover.png' } }));

        await pickFile(vm, 'uploadCover');

        expect(fetch.mock.calls.at(-1)[0]).toBe('https://example.test/wp-json/fluent-community/v2/feeds/media-upload');
        expect(vm.event.cover_url).toBe('https://example.test/uploads/cover.png');
        expect(vm.uploading).toBe(false);
    });

    it('puts an uploaded image into the description', async () => {
        const { vm } = await createPage();
        fetch.mockResolvedValueOnce(json({ media: { url: 'https://example.test/uploads/photo.png' } }));

        await pickFile(vm, 'uploadImage');

        expect(insertContent).toHaveBeenCalledWith('<img src="https://example.test/uploads/photo.png" alt="">');
    });

    it("shows fcom's reason when an upload is refused", async () => {
        const { vm } = await createPage();
        fetch.mockResolvedValueOnce(json({ message: 'File type not allowed.' }, 422));

        await pickFile(vm, 'uploadCover');

        expect(ElNotification).toHaveBeenCalledWith({ type: 'error', title: 'Error', message: 'File type not allowed.' });
        expect(vm.event.cover_url).toBe('');
    });

    it('goes back to wherever the page was opened from', async () => {
        const { vm, props } = await createPage();

        vm.goBack();

        expect(props.router.back).toHaveBeenCalled();
        expect(props.router.push).not.toHaveBeenCalled();
    });
});
