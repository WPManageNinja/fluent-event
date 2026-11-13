import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import MarkdownEditor from '@/components/MarkdownEditor.vue';

// Milkdown itself is mocked: these pin what the component hands it and what it does with the answers.
const milkdown = vi.hoisted(() => ({ instances: [], configs: [] }));

vi.mock('@milkdown/crepe', () => {
    class Crepe {
        static Feature = {
            Placeholder: 'placeholder', ImageBlock: 'image-block', LinkTooltip: 'link-tooltip', CodeMirror: 'code-mirror', BlockEdit: 'block-edit', Table: 'table',
        };

        constructor(options) {
            this.options = options;
            this.destroy = vi.fn();
            this.create = vi.fn(() => Promise.resolve());
            this.editor = { config: (fn) => (milkdown.configs.push(fn), this.editor), use: () => this.editor };
            milkdown.instances.push(this);
        }
    }

    return { Crepe };
});
vi.mock('@milkdown/kit/plugin/listener', () => ({ listener: {}, listenerCtx: 'listenerCtx' }));
vi.mock('@milkdown/kit/plugin/upload', () => ({ upload: {}, uploadConfig: { key: 'uploadConfig' } }));
vi.mock('@milkdown/crepe/theme/common/style.css', () => ({}));
vi.mock('@milkdown/crepe/theme/frame.css', () => ({}));

const editor = async (props = {}) => {
    const upload = vi.fn((file) => Promise.resolve(`https://example.test/uploads/${file.name}`));
    const wrapper = mount(MarkdownEditor, {
        props: { modelValue: '**Bring** a tripod', upload, ...props },
        global: { mocks: { $t: (text) => text }, directives: { loading: {} } },
    });
    await flushPromises();

    return { wrapper, upload, crepe: milkdown.instances.at(-1) };
};

// Runs the component's editor config against a stand-in context, returning what it registered.
const configured = () => {
    const found = {};
    const ctx = {
        get: () => ({ markdownUpdated: (fn) => (found.onMarkdown = fn) }),
        update: (key, fn) => (found.uploader = fn({}).uploader),
    };
    milkdown.configs.at(-1)(ctx);

    return found;
};

beforeEach(() => {
    milkdown.instances.length = 0;
    milkdown.configs.length = 0;
});

describe('MarkdownEditor', () => {
    it('starts Milkdown on the stored markdown, and reports every change as markdown', async () => {
        const { wrapper, crepe } = await editor();

        expect(crepe.options.defaultValue).toBe('**Bring** a tripod');
        expect(crepe.create).toHaveBeenCalled();
        expect(wrapper.vm.ready).toBe(true);

        configured().onMarkdown(null, '**Bring** a tripod and a torch');
        expect(wrapper.emitted('update:modelValue')).toEqual([['**Bring** a tripod and a torch']]);
    });

    it("sends the image block's uploads, and pasted or dropped images, through the caller's upload", async () => {
        const { crepe, upload } = await editor();
        const create = vi.fn((attrs) => attrs);
        const schema = { nodes: { 'image-block': { create } } };
        const photo = new File(['x'], 'walk.png', { type: 'image/png' });
        const notes = new File(['x'], 'notes.txt', { type: 'text/plain' });

        expect(crepe.options.featureConfigs['image-block'].onUpload).toBe(upload);

        const nodes = await configured().uploader([photo, notes], schema);
        expect(upload).toHaveBeenCalledTimes(1);
        expect(nodes).toEqual([{ src: 'https://example.test/uploads/walk.png' }]);
    });

    it('skips a pasted image whose upload fails, keeping the rest', async () => {
        const upload = vi.fn((file) => (file.name === 'bad.png' ? Promise.reject(new Error('Too large')) : Promise.resolve('https://example.test/ok.png')));
        await editor({ upload });
        const schema = { nodes: { 'image-block': { create: (attrs) => attrs } } };

        const nodes = await configured().uploader([new File(['x'], 'bad.png', { type: 'image/png' }), new File(['x'], 'ok.png', { type: 'image/png' })], schema);

        expect(nodes).toEqual([{ src: 'https://example.test/ok.png' }]);
    });

    it("turns tables off and leaves them and task lists out of the slash menu", async () => {
        const { crepe } = await editor();
        const groups = {
            list: { items: [{ key: 'bullet-list' }, { key: 'ordered-list' }, { key: 'todo-list' }] },
            advanced: { items: [{ key: 'image' }, { key: 'code' }, { key: 'table' }] },
        };

        expect(crepe.options.features).toEqual({ table: false });
        crepe.options.featureConfigs['block-edit'].buildMenu({ getGroup: (key) => ({ group: groups[key] }) });

        expect(groups.list.items.map((item) => item.key)).toEqual(['bullet-list', 'ordered-list']);
        expect(groups.advanced.items.map((item) => item.key)).toEqual(['image', 'code']);
    });

    // Crepe's toolbar, image and link buttons have no type, and its inputs submit on Enter.
    it("never submits the form it sits in from Crepe's buttons or inputs", async () => {
        const onSubmit = vi.fn((event) => event.preventDefault());
        const form = document.createElement('form');
        form.addEventListener('submit', onSubmit);
        document.body.appendChild(form);
        const wrapper = mount(MarkdownEditor, {
            attachTo: form,
            props: { upload: vi.fn() },
            global: { mocks: { $t: (text) => text }, directives: { loading: {} } },
        });
        await flushPromises();

        const root = wrapper.find('.fe-markdown-editor__root').element;
        // The table's column tools are buttons inside its drag handle, itself a button.
        root.innerHTML = '<button class="toolbar-item">B</button><input class="caption-input">'
            + '<button class="cell-handle"><span class="button-group"><button class="align">L</button></span></button>';

        root.querySelector('.toolbar-item').click();
        root.querySelector('.align').click();
        expect(onSubmit).not.toHaveBeenCalled();
        expect([...root.querySelectorAll('button')].map((button) => button.type)).toEqual(['button', 'button', 'button']);

        const enter = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });
        root.querySelector('input').dispatchEvent(enter);
        expect(enter.defaultPrevented).toBe(true);

        wrapper.unmount();
        form.remove();
    });

    it('destroys the editor with the form', async () => {
        const { wrapper, crepe } = await editor();

        wrapper.unmount();

        expect(crepe.destroy).toHaveBeenCalled();
    });

    it('never starts Milkdown for a form closed before it finished downloading', async () => {
        const wrapper = mount(MarkdownEditor, {
            props: { upload: vi.fn() },
            global: { mocks: { $t: (text) => text }, directives: { loading: {} } },
        });

        wrapper.unmount();
        await flushPromises();

        expect(milkdown.instances).toHaveLength(0);
    });
});
