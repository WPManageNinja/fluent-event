import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import Venues from '@/modules/venues/index.vue';
import VenueFormModal from '@/modules/venues/parts/VenueFormModal.vue';

vi.mock('@/utils/http/Rest.js', () => ({ default: { get: vi.fn() } }));

const kingsway = {
    id: 4,
    name: 'Kingsway Hall',
    address: '12 Kingsway',
    city: 'London',
    zip_code: 'WC2B 6XF',
    country: 'United Kingdom',
    map_url: 'https://maps.example.com/kingsway'
};

const page = (venues = [kingsway], canWrite = true) => {
    const requestServer = vi.fn(options => options.onSuccess?.({ venues: { data: venues, total: venues.length } }));
    const wrapper = shallowMount(Venues, {
        global: {
            mixins: [main, { methods: { $requestServer: requestServer, $changeTitle: vi.fn(), $userCan: () => canWrite } }],
            stubs: { transition: false }
        }
    });

    return { wrapper, vm: wrapper.vm, requestServer };
};

const modal = (venue = null) => {
    const requestServer = vi.fn();
    const wrapper = shallowMount(VenueFormModal, {
        props: { modelValue: true, venue },
        global: { mixins: [main, { methods: { $requestServer: requestServer } }] }
    });

    wrapper.vm.resetForm();

    return { wrapper, vm: wrapper.vm, requestServer };
};

describe('Venues page', () => {
    it('loads the first page and joins the address parts into one line', () => {
        const { vm, requestServer } = page();

        expect(requestServer.mock.calls[0][0]).toMatchObject({ path: 'venues', payload: { search: '', page: 1, per_page: 100 } });
        expect(vm.rows[0].location).toBe('12 Kingsway, London WC2B 6XF, United Kingdom');
        expect(vm.rows[0].mapUrl).toBe('https://maps.example.com/kingsway');
    });

    it('leaves out empty address parts and any map link that is not http', () => {
        const { vm } = page([{ ...kingsway, city: '', zip_code: '', country: '', map_url: 'javascript:alert(1)' }]);

        expect(vm.rows[0].location).toBe('12 Kingsway');
        expect(vm.rows[0].mapUrl).toBe('');
    });

    it('starts a search from the first page', () => {
        const { vm, requestServer } = page();
        vm.pagination.current_page = 3;
        vm.query.search = 'hall';

        vm.search();

        expect(requestServer.mock.calls.at(-1)[0].payload).toMatchObject({ search: 'hall', page: 1 });
    });

    it('offers add, edit and delete to a venue writer only', async () => {
        const writer = page().wrapper;
        await writer.vm.$nextTick();
        expect(writer.find('.fe-venues__actions').exists()).toBe(true);
        expect(writer.find('.fe-page__actions').exists()).toBe(true);

        const { wrapper, vm } = page([kingsway], false);
        await vm.$nextTick();
        expect(wrapper.find('.fe-venues__row:not(.fe-venues__row--head)').exists()).toBe(true);

        expect(vm.canWrite).toBe(false);
        expect(wrapper.find('.fe-venues__actions').exists()).toBe(false);
        expect(wrapper.find('.fe-page__actions').exists()).toBe(false);
    });
});

describe('VenueFormModal', () => {
    it('posts a new venue with every field', () => {
        const { vm, requestServer } = modal();
        Object.assign(vm.form, { name: 'Corner Café', address: '1 High Street', city: 'Leeds' });

        vm.save();

        expect(requestServer.mock.calls[0][0]).toMatchObject({
            method: 'post',
            path: 'venues',
            payload: { name: 'Corner Café', address: '1 High Street', city: 'Leeds', zip_code: '', country: '', map_url: '' }
        });
    });

    it('patches the venue being edited, starting from its saved fields', () => {
        const { vm, requestServer } = modal(kingsway);

        expect(vm.form).toEqual({
            name: 'Kingsway Hall',
            address: '12 Kingsway',
            city: 'London',
            zip_code: 'WC2B 6XF',
            country: 'United Kingdom',
            map_url: 'https://maps.example.com/kingsway'
        });

        vm.save();

        expect(requestServer.mock.calls[0][0]).toMatchObject({ method: 'patch', path: 'venues/4' });
    });
});
