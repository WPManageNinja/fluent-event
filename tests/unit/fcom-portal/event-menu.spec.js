import { describe, it, expect, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import EventMenu from '../../../resources/fcom-portal/EventMenu';

const editable = { id: 5, edit_url: 'https://example.test/wp-admin/admin.php?page=fluent-event#/events/5/edit' };

let wrapper;
const menu = (event = editable) => {
    wrapper = mount(EventMenu, { props: { event }, attachTo: document.body });
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

    it("opens on the event's edit link and moves focus to it", async () => {
        menu();

        await wrapper.find('button').trigger('click');
        await wrapper.vm.$nextTick();

        const item = wrapper.find('[role="menuitem"]');
        expect(item.attributes('href')).toBe(editable.edit_url);
        expect(document.activeElement).toBe(item.element);
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
