<template>
    <div v-loading="!ready" class="fe-markdown-editor" @click.capture="keepButtonInEditor" @keydown.enter.capture="keepEnterInEditor">
        <div ref="root" class="fe-markdown-editor__root" :aria-labelledby="ariaLabelledby || null"></div>
    </div>
</template>

<script type="text/javascript">
    // Milkdown is heavy, so it downloads only when a form with this editor opens.
    const loadMilkdown = () => Promise.all([
        import('@milkdown/crepe'),
        import('@milkdown/kit/plugin/listener'),
        import('@milkdown/kit/plugin/upload'),
        import('@milkdown/crepe/theme/common/style.css'),
        import('@milkdown/crepe/theme/frame.css'),
    ]);

    export default {
        name: 'MarkdownEditor',
        props: {
            modelValue: {
                type: String,
                default: '',
            },
            placeholder: {
                type: String,
                default: '',
            },
            // Id of the element naming this editor; the caller owns the label.
            ariaLabelledby: {
                type: String,
                default: '',
            },
            // Takes an image file and resolves to its URL; the caller decides where images live.
            upload: {
                type: Function,
                required: true,
            },
        },
        emits: ['update:modelValue'],
        data() {
            return {
                ready: false,
            };
        },
        async mounted() {
            const [{ Crepe }, { listener, listenerCtx }, { upload, uploadConfig }] = await loadMilkdown();

            if (this.gone) {
                return;
            }

            this.crepe = new Crepe({
                root: this.$refs.root,
                defaultValue: this.modelValue,
                featureConfigs: this.featureConfigs(Crepe.Feature),
            });

            this.crepe.editor
                .config((ctx) => {
                    ctx.get(listenerCtx).markdownUpdated((_, markdown) => this.$emit('update:modelValue', markdown));
                    ctx.update(uploadConfig.key, (config) => ({ ...config, uploader: this.uploadFiles }));
                })
                .use(listener)
                .use(upload);

            await this.crepe.create();

            if (this.gone) {
                this.crepe.destroy();
                return;
            }

            this.ready = true;
        },
        beforeUnmount() {
            this.gone = true;

            if (this.ready) {
                this.crepe.destroy();
            }
        },
        methods: {
            // Crepe's buttons carry no type, so inside a <form> a click would submit it; its table tools
            // are even buttons inside a button. All are typed before the click's default action reads it.
            keepButtonInEditor() {
                this.$el.querySelectorAll('button:not([type])').forEach((button) => {
                    button.type = 'button';
                });
            },
            // Enter in Crepe's link and caption inputs would submit the form too; Crepe's own Enter
            // handlers still run, only the browser's form submit is cancelled.
            keepEnterInEditor(event) {
                if (event.target.tagName === 'INPUT') {
                    event.preventDefault();
                }
            },
            featureConfigs(Feature) {
                return {
                    [Feature.Placeholder]: { text: this.placeholder, mode: 'doc' },
                    [Feature.ImageBlock]: {
                        onUpload: this.upload,
                        blockUploadButton: () => this.$t('Upload image'),
                        blockUploadPlaceholderText: this.$t('or paste an image link'),
                        blockCaptionPlaceholderText: this.$t('Write a caption'),
                        inlineUploadButton: () => this.$t('Upload image'),
                        inlineUploadPlaceholderText: this.$t('or paste an image link'),
                    },
                    [Feature.LinkTooltip]: { inputPlaceholder: this.$t('Paste a link') },
                    [Feature.CodeMirror]: {
                        searchPlaceholder: this.$t('Search languages'),
                        noResultText: this.$t('No languages found'),
                    },
                    [Feature.BlockEdit]: {
                        slashMenuTextGroupLabel: this.$t('Text'),
                        slashMenuTextLabel: this.$t('Text'),
                        slashMenuH1Label: this.$t('Heading 1'),
                        slashMenuH2Label: this.$t('Heading 2'),
                        slashMenuH3Label: this.$t('Heading 3'),
                        slashMenuH4Label: this.$t('Heading 4'),
                        slashMenuH5Label: this.$t('Heading 5'),
                        slashMenuH6Label: this.$t('Heading 6'),
                        slashMenuQuoteLabel: this.$t('Quote'),
                        slashMenuDividerLabel: this.$t('Divider'),
                        slashMenuListGroupLabel: this.$t('List'),
                        slashMenuBulletListLabel: this.$t('Bullet list'),
                        slashMenuOrderedListLabel: this.$t('Numbered list'),
                        slashMenuAdvancedGroupLabel: this.$t('Advanced'),
                        slashMenuImageLabel: this.$t('Image'),
                        slashMenuCodeBlockLabel: this.$t('Code'),
                        slashMenuTableLabel: this.$t('Table'),
                        // The server's markdown renderer has no task lists, so the menu doesn't offer one.
                        buildMenu: (builder) => {
                            const list = builder.getGroup('list').group;
                            list.items = list.items.filter((item) => item.key !== 'todo-list');
                        },
                    },
                };
            },
            // Pasted or dropped images take the same upload as the image block's button.
            async uploadFiles(files, schema) {
                const nodes = [];

                for (const file of Array.from(files)) {
                    if (!file.type.startsWith('image/')) {
                        continue;
                    }

                    try {
                        nodes.push(schema.nodes['image-block'].create({ src: await this.upload(file) }));
                    } catch (error) {
                        // The caller's upload already said why; the other files still go in.
                    }
                }

                return nodes;
            },
        },
    };
</script>
