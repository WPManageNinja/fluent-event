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

const createPage = async (Page = CreateEventPage) => {
    const props = { spaceSlug: 'design', router: { push: vi.fn() }, onChange: vi.fn(), submitPost: vi.fn() };
    const wrapper = mount(Page, {
        props,
        global: {
            mocks: { $t: t },
            stubs: {
                ElFormItem: { template: '<div><slot /></div>' },
                ElInput: { template: '<input />', methods: { focus() {} } },
                ElInputNumber: { template: '<input />' },
                WpEditor: { template: '<div></div>', methods: { insertContent } }
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
    it('hands the composer a full event as soon as it opens', async () => {
        const { props } = await createPage();

        expect(props.onChange).toHaveBeenCalledWith(expect.objectContaining({
            start_at: '2026-10-14 10:00:00',
            end_at: '2026-10-14 11:00:00',
            venue_type: 'offline',
            venue_id: null,
            entry_type: 'rsvp',
            show_guest_count: 'no',
            capacity: 0,
            waitlist: 'no',
            registration_opens: null,
            registration_closes: null
        }));
    });

    it('hands the composer every later change', async () => {
        const { vm, props } = await createPage();

        vm.event.title = 'Design review';
        await vm.$nextTick();

        expect(props.onChange.mock.calls.at(-1)[0].title).toBe('Design review');
    });

    it('pulls the end date forward when the start moves past it', async () => {
        const { vm } = await createPage();
        vm.event.start_date = '2026-10-20';

        vm.syncEndDate();

        expect(vm.event.end_date).toBe('2026-10-20');
    });

    it("submits through fcom's composer and mirrors its posting state on the button", async () => {
        const { vm, props } = await createPage();

        vm.submit();
        const onPosting = props.submitPost.mock.calls[0][0];

        onPosting(true);
        expect(vm.saving).toBe(true);
        onPosting(false);
        expect(vm.saving).toBe(false);
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

    it("returns to the space's events", async () => {
        const { vm, props } = await createPage();

        vm.goBack();

        expect(props.router.push).toHaveBeenCalledWith({ name: 'fe_space_events', params: { space: 'design' } });
    });
});
