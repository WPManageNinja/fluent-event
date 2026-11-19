import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { compile } from 'sass';
import { ElNotification } from 'element-plus';
import { t } from '../../../resources/fcom-portal/utils';
import CreateOrUpdateEventPage from '../../../resources/fcom-portal/app/CreateOrUpdateEventPage.vue';

vi.mock('element-plus', async (importOriginal) => ({ ...(await importOriginal()), ElNotification: vi.fn() }));

const json = (body, status = 200) => new Response(JSON.stringify(body), { status });

// v-loading is never registered in the portal app (see events-page.spec.js); drop only that warning.
const warnHandler = (message, instance, trace) => {
    if (!message.includes('Failed to resolve directive: loading')) {
        console.warn(`[Vue warn]: ${message}${trace}`);
    }
};

const createPage = async (Page = CreateOrUpdateEventPage, extraProps = {}, extraStubs = {}) => {
    const props = { router: { push: vi.fn(), back: vi.fn() }, onChange: vi.fn(), submitPost: vi.fn(), onPlacement: vi.fn(),
        spaces: [{ slug: 'design', title: 'Design Team', emoji: '🎨' }], initialSpace: 'design', onSpace: vi.fn(), ...extraProps };
    const wrapper = mount(Page, {
        props,
        global: {
            mocks: { $t: t },
            stubs: {
                ElFormItem: { template: '<div><slot /></div>' },
                ElInput: { template: '<input />', methods: { focus() {} } },
                ElInputNumber: { template: '<input />' },
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
    vi.stubGlobal('fetch', vi.fn(async () => json({})));
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-14T09:20:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('CreateOrUpdateEventPage', () => {
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
            const { wrapper } = await createPage(CreateOrUpdateEventPage, { globalPosts: false }, slotStubs);
            const tooltip = wrapper.findAllComponents({ name: 'ElTooltip' })[0];

            expect(placementGroup(wrapper).props('modelValue')).toBe('space');
            expect(radio(wrapper, 'global').props('disabled')).toBe(true);
            expect(radio(wrapper, 'space').props('disabled')).toBeFalsy();
            expect(tooltip.props('disabled')).toBe(false);
            expect(tooltip.props('content')).toContain('Global Post Status');
        });

        it('lists In a space before Community-wide', async () => {
            const { wrapper } = await createPage(CreateOrUpdateEventPage, {}, slotStubs);

            expect(wrapper.findAllComponents({ name: 'ElRadio' }).slice(0, 2).map(r => r.props('value'))).toEqual(['space', 'global']);
        });

        it('picks the space on the page, starting from the linked one, and reports it to the shell', async () => {
            const { wrapper, vm, props } = await createPage();
            const select = () => wrapper.findComponent({ name: 'ElSelect' });

            expect(vm.space).toBe('design');
            select().vm.$emit('change', 'design');
            expect(props.onSpace).toHaveBeenCalledWith('design');

            vm.placement = 'global';
            await flushPromises();
            expect(select().exists()).toBe(false);
        });

        it("starts with no space when the linked one isn't one the member can post in", async () => {
            const { vm } = await createPage(CreateOrUpdateEventPage, { initialSpace: 'secret' });

            expect(vm.space).toBe('');
        });

        it('keeps the tooltip off while Community-wide can be picked', async () => {
            const { wrapper } = await createPage(CreateOrUpdateEventPage, {}, slotStubs);

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
            show_guest_count: 'yes',
            start_display: 'relative'
        }));
        // The description comes from fcom's editor, which the shell owns; the page sends none.
        expect(props.onChange.mock.calls[0][0]).not.toHaveProperty('description');
        expect(props.onChange.mock.calls[0][0]).not.toHaveProperty('description_md');
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

        vm.space = '';
        refused('Choose a space for this event.');
        vm.space = 'design';

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

    it('uploads a cover to the WordPress media library and keeps its URL', async () => {
        const { vm } = await createPage();
        fetch.mockResolvedValueOnce(json({ id: 9, source_url: 'https://example.test/uploads/cover.png' }));

        await pickFile(vm, 'uploadCover');

        expect(fetch.mock.calls.at(-1)[0]).toBe('https://example.test/wp-json/wp/v2/media');
        expect(vm.event.cover_url).toBe('https://example.test/uploads/cover.png');
        expect(vm.uploading).toBe(false);
    });

    it("leaves a labelled spot for fcom's markdown editor, which the shell fills", async () => {
        const { wrapper } = await createPage();
        const spot = wrapper.find('#fe-create-event-description');

        expect(spot.exists()).toBe(true);
        expect(spot.attributes('aria-labelledby')).toBe('fe-create-event-description-label');
    });

    it('shows the reason when an upload is refused', async () => {
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

    describe('editing', () => {
        const stored = {
            id: 5,
            title: 'Night walk',
            calendar_id: 3,
            status: 'private',
            timezone: 'Europe/London',
            venue_type: 'online',
            start_at_local: '2026-11-20 18:30:00',
            end_at_local: '2026-11-20 20:00:00',
            meta: {
                description_md: '**Bring** a torch',
                online_meeting_url: 'https://meet.example.test/walk',
                venue: '',
                address: '',
                cover_url: 'https://example.test/uploads/walk.png',
                entry_type: 'open',
                show_guest_count: 'no',
                capacity: 25,
                show_spots_left: 'yes',
                start_display: 'countdown'
            }
        };

        // The edit route passes only these; the create-only props fall back to their defaults.
        const mountEdit = async (response, extraProps) => {
            const props = { router: { push: vi.fn(), back: vi.fn() }, eventSlug: 'night-walk', onLoaded: vi.fn(), getDescription: () => '**Bring** a torch', ...extraProps };
            fetch.mockReturnValueOnce(response instanceof Promise ? response : Promise.resolve(response || json({ event: stored })));
            ElNotification.mockClear();

            const wrapper = mount(CreateOrUpdateEventPage, {
                props,
                global: {
                    mocks: { $t: t },
                    stubs: {
                        ElFormItem: { template: '<div><slot /></div>' },
                        ElInput: { template: '<input />', methods: { focus() {} } },
                        ElInputNumber: { template: '<input />' },
                        ElButton: { template: '<button><slot /></button>' }
                    },
                    config: { warnHandler }
                }
            });
            await flushPromises();

            return { wrapper, vm: wrapper.vm, props };
        };

        it('shows a loading state while the event loads', async () => {
            const { wrapper } = await mountEdit(new Promise(() => {}));

            expect(wrapper.findComponent({ name: 'ElSkeleton' }).exists()).toBe(true);
            expect(wrapper.find('.fe-create-event__form').exists()).toBe(false);
            expect(wrapper.find('.fe-create-event__actions').exists()).toBe(false);
        });

        it("fills the form from the admin event endpoint and hands the event to the shell", async () => {
            const { vm, props } = await mountEdit();

            expect(fetch.mock.calls[0][0]).toBe('https://example.test/wp-json/fluent-event/v2/events/by-slug?slug=night-walk');
            expect(vm.event).toEqual({
                title: 'Night walk',
                start_date: '2026-11-20',
                start_time: '18:30',
                end_date: '2026-11-20',
                end_time: '20:00',
                timezone: 'Europe/London',
                venue_type: 'online',
                venue: '',
                address: '',
                online_meeting_url: 'https://meet.example.test/walk',
                cover_url: 'https://example.test/uploads/walk.png',
                entry_type: 'open',
                show_guest_count: 'no',
                capacity: 25,
                show_spots_left: 'yes',
                start_display: 'countdown'
            });
            expect(props.onLoaded).toHaveBeenCalledWith(stored, null);
        });

        // Space and post text are fcom's post, edited there.
        it('leaves out where to post and the feed post, and says Edit / Update', async () => {
            const { wrapper } = await mountEdit();

            expect(wrapper.text()).not.toContain('Where do you want to post this event?');
            expect(wrapper.text()).not.toContain('Feed Post');
            expect(wrapper.find('#fe-create-event-composer').exists()).toBe(false);
            expect(wrapper.find('#fe-create-event-description').exists()).toBe(true);
            expect(wrapper.find('.fe-create-event__heading').text()).toBe('Edit Event');
            expect(wrapper.find('.fe-create-event__actions').text()).toBe('Update Event');
        });

        it('points to the post for its text and space, when the event has one', async () => {
            const { wrapper } = await mountEdit(json({ event: { ...stored, feed_url: 'https://example.test/portal/space/design/post/night-walk' } }));

            expect(wrapper.find('.fe-create-event__notice').text()).toContain('To change the post text or its space, edit the post itself.');
            expect(wrapper.find('.fe-create-event__notice-link').attributes('href')).toBe('https://example.test/portal/space/design/post/night-walk');

            const { wrapper: unlinked } = await mountEdit();

            expect(unlinked.find('.fe-create-event__notice').exists()).toBe(false);
        });

        it('saves through PATCH with the calendar and status it loaded, and goes back', async () => {
            const { vm, props } = await mountEdit(undefined, { getDescription: () => '**Bring** a torch and boots' });
            vm.event.title = 'Night walk II';
            fetch.mockResolvedValueOnce(json({ message: 'Event has been successfully updated' }));

            vm.submit();
            await flushPromises();

            const [url, options] = fetch.mock.calls.at(-1);
            expect(url).toBe('https://example.test/wp-json/fluent-event/v2/events/5');
            expect(options.method).toBe('PATCH');
            expect(JSON.parse(options.body)).toEqual({
                calendar_id: 3,
                status: 'private',
                title: 'Night walk II',
                description_md: '**Bring** a torch and boots',
                start_at: '2026-11-20 18:30:00',
                end_at: '2026-11-20 20:00:00',
                timezone: 'Europe/London',
                venue_type: 'online',
                cover_url: 'https://example.test/uploads/walk.png',
                online_meeting_url: 'https://meet.example.test/walk',
                venue: '',
                address: '',
                entry_type: 'open',
                show_guest_count: 'no',
                capacity: 25,
                show_spots_left: 'yes',
                start_display: 'countdown'
            });
            expect(ElNotification).toHaveBeenCalledWith({ type: 'success', message: 'Event has been successfully updated' });
            expect(props.router.back).toHaveBeenCalled();
        });

        it('stays on the page and says why when the update is refused', async () => {
            const { vm, props } = await mountEdit();
            fetch.mockResolvedValueOnce(json({ message: 'The title may not be greater than 255 characters.' }, 422));

            vm.submit();
            await flushPromises();

            expect(ElNotification).toHaveBeenCalledWith({ type: 'error', title: 'Error', message: 'The title may not be greater than 255 characters.' });
            expect(props.router.back).not.toHaveBeenCalled();
            expect(vm.saving).toBe(false);
        });

        it('needs no space, but still checks the event', async () => {
            const { vm } = await mountEdit();

            expect(vm.space).toBe('');
            expect(vm.validateEvent()).toBe('');

            vm.event.title = ' ';
            expect(vm.validateEvent()).toBe('Event title is required.');
        });

        it("says the event isn't available when it can't be loaded", async () => {
            const { wrapper, props } = await mountEdit(json({ message: 'Event not found' }, 404));

            expect(ElNotification).toHaveBeenCalledWith({ type: 'error', title: 'Error', message: 'Event not found' });
            expect(wrapper.findComponent({ name: 'ElEmpty' }).props('description')).toBe('This event is not available.');
            expect(wrapper.find('.fe-create-event__form').exists()).toBe(false);
            expect(wrapper.find('.fe-create-event__actions').exists()).toBe(false);
            expect(props.onLoaded).not.toHaveBeenCalled();
        });

        // A route param arrives decoded; in the path, a crafted `..%2F` slug would reach another REST route.
        it('sends the slug as a query value, never as part of the path', async () => {
            await mountEdit(undefined, { eventSlug: '../../wp/v2/users/me' });

            expect(fetch.mock.calls[0][0]).toBe('https://example.test/wp-json/fluent-event/v2/events/by-slug?slug=..%2F..%2Fwp%2Fv2%2Fusers%2Fme');
        });
    });

    describe('cloning', () => {
        const source = {
            id: 5,
            title: 'Night walk',
            timezone: 'Europe/London',
            venue_type: 'offline',
            start_at_local: '2026-11-20 18:30:00',
            end_at_local: '2026-11-20 20:00:00',
            feed_slug: 'night-walk-post',
            meta: { venue: 'Kingsway Hall', entry_type: 'open', capacity: 25, show_spots_left: 'yes', start_display: 'countdown' }
        };
        const post = { slug: 'night-walk-post', title: 'Walk', message: 'Join us', space: { slug: 'design' } };

        const mountClone = async (event = source, feed = post, extraProps = {}) => {
            fetch.mockResolvedValueOnce(json({ event })).mockResolvedValueOnce(json({ feed }));

            return createPage(CreateOrUpdateEventPage, { cloneSlug: 'night-walk', onLoaded: vi.fn(), initialSpace: '', ...extraProps });
        };

        it('prefills a new event from the source and its post, and stays a create form', async () => {
            const { wrapper, vm, props } = await mountClone();

            expect(fetch.mock.calls[0][0]).toBe('https://example.test/wp-json/fluent-event/v2/events/by-slug?slug=night-walk');
            expect(fetch.mock.calls[1][0]).toBe('https://example.test/wp-json/fluent-community/v2/feeds/night-walk-post/by-slug?context=edit');
            expect(vm.event).toMatchObject({ title: 'Night walk', start_date: '2026-11-20', start_time: '18:30', venue: 'Kingsway Hall', entry_type: 'open', capacity: 25, start_display: 'countdown' });
            expect(props.onLoaded).toHaveBeenCalledWith(source, post);
            expect(vm.loadedEvent).toBe(null);
            expect(wrapper.find('.fe-create-event__heading').text()).toBe('Create Event');
            expect(wrapper.find('#fe-create-event-composer').exists()).toBe(true);
        });

        it('shows only the skeleton while the source loads, not a blank form under it', async () => {
            fetch.mockReturnValueOnce(new Promise(() => {}));

            const { wrapper } = await createPage(CreateOrUpdateEventPage, { cloneSlug: 'night-walk', onLoaded: vi.fn() });

            expect(wrapper.findComponent({ name: 'ElSkeleton' }).exists()).toBe(true);
            expect(wrapper.find('.fe-create-event__form').exists()).toBe(false);
            expect(wrapper.find('.fe-create-event__actions').exists()).toBe(false);
        });

        it("posts where the source's post was", async () => {
            const { vm, props } = await mountClone();

            expect(vm.placement).toBe('space');
            expect(vm.space).toBe('design');
            expect(props.onSpace).toHaveBeenCalledWith('design');
        });

        it('posts community-wide when the source post was', async () => {
            const { vm, props } = await mountClone(source, { ...post, space: null });

            expect(vm.placement).toBe('global');
            expect(props.onPlacement).toHaveBeenCalledWith('global');
        });

        it('copies just the event when it has no post', async () => {
            const { vm, props } = await mountClone({ ...source, feed_slug: undefined });

            expect(fetch).toHaveBeenCalledTimes(1);
            expect(vm.event.title).toBe('Night walk');
            expect(props.onLoaded).toHaveBeenCalledWith({ ...source, feed_slug: undefined }, null);
        });

        // The editors still need mounting, so a blank create form stays usable.
        it('says why when the source cannot be loaded, and leaves a blank create form', async () => {
            fetch.mockResolvedValueOnce(json({ message: 'Event not found' }, 404));
            ElNotification.mockClear();

            const { wrapper, vm, props } = await createPage(CreateOrUpdateEventPage, { cloneSlug: 'gone', onLoaded: vi.fn() });

            expect(ElNotification).toHaveBeenCalledWith({ type: 'error', title: 'Error', message: 'Event not found' });
            expect(vm.event.title).toBe('');
            expect(wrapper.find('.fe-create-event__form').exists()).toBe(true);
            expect(props.onLoaded).toHaveBeenCalledWith(null, null);
        });
    });

    describe('"Start Time Display" help', () => {
        // The dialog is 560px; fcom's styles cap only its own dialogs, so on a phone it ran off the screen.
        it('keeps the help dialog inside a phone screen', async () => {
            const { wrapper } = await createPage();

            expect(wrapper.findComponent({ name: 'ElDialog' }).classes()).toContain('fe-start-display-help');
            expect(compile('resources/fcom-portal/fcom-portal.scss').css)
                .toMatch(/\.fe-start-display-help\.el-dialog\s*\{\s*max-width:\s*calc\(100vw - 24px\)/);
        });

        // Drawn with the page's own markup, so the plugin ships no screenshots and the help follows the theme.
        it('draws each choice as it shows on the event page', async () => {
            const { wrapper } = await createPage(CreateOrUpdateEventPage, {}, { ElDialog: { template: '<div><slot /></div>' } });
            const previews = wrapper.findAll('.fe-start-display-help__item .fe-start-preview');

            expect(previews.map((preview) => preview.attributes('aria-label'))).toEqual(['Time Left on the event page', 'Countdown on the event page']);
            expect(previews[0].find('.fe-start-preview__status').text()).toBe('Starts in 2 days');
            expect(previews[1].findAll('.fe-start-preview__label').map((label) => label.text())).toEqual(['Days', 'Hours', 'Minutes', 'Seconds']);
            expect(wrapper.find('.fe-start-display-help__item img').exists()).toBe(false);
        });
    });
});
