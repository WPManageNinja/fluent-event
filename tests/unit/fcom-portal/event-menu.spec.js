import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import EventMenu from '../../../resources/fcom-portal/EventMenu';

const editable = { id: 5, edit_url: 'https://example.test/wp-admin/admin.php?page=fluent-event#/events/5/edit' };
const editRoute = { name: 'fe_edit_event', params: { event_id: 5 } };
const fcomRouter = () => ({ resolve: vi.fn(() => ({ href: '/portal/events/5/edit' })), push: vi.fn() });

let wrapper;
let router;
const menu = (event = editable) => {
    router = fcomRouter();
    wrapper = mount(EventMenu, { props: { event, router }, attachTo: document.body });
    return wrapper;
};

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe('EventMenu', () => {
    it('renders nothing for a member who cannot edit the event', () => {
        expect(menu({ id: 5, edit_url: '' }).find('.fe-event-menu').exists()).toBe(false);
    });

    it('names the icon-only trigger for screen readers', () => {
        expect(menu().find('button').attributes('aria-label')).toBe('More actions');
    });

    it("opens on the portal's edit page for the event and moves focus to it", async () => {
        menu();

        await wrapper.find('button').trigger('click');
        await wrapper.vm.$nextTick();

        const item = wrapper.find('[role="menuitem"]');
        expect(router.resolve).toHaveBeenCalledWith(editRoute);
        expect(item.attributes('href')).toBe('/portal/events/5/edit');
        expect(document.activeElement).toBe(item.element);
    });

    // fcom's document click handler would route the link a second time.
    it('edits inside the portal, without the click reaching the page', async () => {
        menu();
        await wrapper.find('button').trigger('click');
        const onDocumentClick = vi.fn();
        document.addEventListener('click', onDocumentClick);

        const click = new MouseEvent('click', { bubbles: true, cancelable: true });
        wrapper.find('[role="menuitem"]').element.dispatchEvent(click);
        document.removeEventListener('click', onDocumentClick);

        expect(router.push).toHaveBeenCalledWith(editRoute);
        expect(click.defaultPrevented).toBe(true);
        expect(onDocumentClick).not.toHaveBeenCalled();
    });

    // The feed card runs on fcom's Vue, which has the router; our page apps pass it in.
    it("falls back to fcom's own router when none is passed", async () => {
        const $router = fcomRouter();
        wrapper = mount(EventMenu, { props: { event: editable }, global: { mocks: { $router } }, attachTo: document.body });

        await wrapper.find('button').trigger('click');
        await wrapper.find('[role="menuitem"]').trigger('click');

        expect($router.push).toHaveBeenCalledWith(editRoute);
    });

    it('closes on Escape and hands focus back to the trigger', async () => {
        menu();
        await wrapper.find('button').trigger('click');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="menu"]').exists()).toBe(false);
        expect(document.activeElement).toBe(wrapper.find('button').element);
    });

    it('closes on a click outside but not on one inside the popover', async () => {
        menu();
        await wrapper.find('button').trigger('click');

        wrapper.find('.fe-event-menu__pop').element.click();
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[role="menu"]').exists()).toBe(true);

        document.body.click();
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[role="menu"]').exists()).toBe(false);
    });
});
