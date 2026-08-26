<template>
    <div class="fe-editor">
        <textarea
            v-if="hasWpEditor"
            :id="editorId"
            class="fe-editor__area"
            :aria-labelledby="ariaLabelledby || null"
        >{{ modelValue }}</textarea>

        <el-input
            v-else
            v-model="plainContent"
            type="textarea"
            :rows="6"
            :placeholder="placeholder"
            :aria-labelledby="ariaLabelledby || null"
        />
    </div>
</template>

<script type="text/javascript">
    let editorCount = 0;

    export default {
        name: 'WpEditor',
        props: {
            modelValue: {
                type: String,
                default: '',
            },
            height: {
                type: Number,
                default: 200,
            },
            placeholder: {
                type: String,
                default: '',
            },
            // Id of the element naming this editor. The caller owns the label,
            // so the same string never has to be translated twice.
            ariaLabelledby: {
                type: String,
                default: '',
            },
            mediaButtons: {
                type: Boolean,
                default: false,
            },
            quickTags: {
                type: Boolean,
                default: false,
            },
            toolbar: {
                type: String,
                default: 'bold,italic,underline,bullist,numlist,link,undo,redo',
            },
        },
        emits: ['update:modelValue'],
        data() {
            return {
                editorId: `fe_editor_${++editorCount}`,
                editor: window.wp?.oldEditor || window.wp?.editor,
                hasWpEditor: !!(window.wp?.editor?.autop || window.wp?.oldEditor),
                plainContent: this.modelValue,
                themeObserver: null,
            };
        },
        watch: {
            plainContent(value) {
                this.$emit('update:modelValue', value);
            },
            modelValue(value) {
                if (!this.hasWpEditor) {
                    this.plainContent = value;
                    return;
                }

                if (this.editor.getContent(this.editorId) === value) {
                    return;
                }

                window.tinymce?.get(this.editorId)?.setContent(value || '');
            },
        },
        methods: {
            themeContentStyle() {
                if (!document.documentElement.classList.contains('dark')) {
                    return '';
                }

                return 'html,body{background-color:#11171d;color:#f0f3f5;}'
                    + '*{color:#f0f3f5;border-color:#253241;}'
                    + 'a{color:#8ab4f8;}';
            },
            applyThemeStyle() {
                const doc = window.tinymce?.get(this.editorId)?.getDoc();

                if (!doc) {
                    return;
                }

                let style = doc.getElementById('fe-editor-theme');

                if (!style) {
                    style = doc.createElement('style');
                    style.id = 'fe-editor-theme';
                    doc.head.appendChild(style);
                }

                style.textContent = this.themeContentStyle();
            },
            initEditor() {
                if (!this.hasWpEditor) {
                    return;
                }

                this.editor.remove(this.editorId);

                this.editor.initialize(this.editorId, {
                    mediaButtons: this.mediaButtons,
                    quicktags: this.quickTags,
                    tinymce: {
                        height: this.height,
                        toolbar1: this.toolbar,
                        statusbar: true,
                        elementpath: false,
                        resize: true,
                        content_style: this.themeContentStyle(),
                        setup: (editor) => {
                            editor.on('change keyup', this.emitContent);

                            // TinyMCE edits inside an iframe, and the textarea's
                            // association does not reach it. Name the iframe too,
                            // or the Visual tab is an unlabelled control.
                            editor.on('init', () => {
                                if (this.ariaLabelledby) {
                                    editor.iframeElement?.setAttribute(
                                        'aria-labelledby', this.ariaLabelledby
                                    );
                                }
                            });
                        },
                    },
                });

                jQuery(`#${this.editorId}`).on('change', this.emitContent);
            },
            emitContent() {
                this.$emit(
                    'update:modelValue',
                    this.editor.getContent(this.editorId)
                );
            },
        },
        mounted() {
            this.initEditor();

            if (!this.hasWpEditor) {
                return;
            }

            this.themeObserver = new MutationObserver(this.applyThemeStyle);
            this.themeObserver.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class'],
            });
        },
        beforeUnmount() {
            if (!this.hasWpEditor) {
                return;
            }

            this.themeObserver?.disconnect();

            jQuery(`#${this.editorId}`).off('change', this.emitContent);
            this.editor.remove(this.editorId);
        },
    };
</script>
