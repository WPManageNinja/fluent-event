<template>
    <div class="fe-editor">
        <textarea
            v-if="hasWpEditor"
            :id="editorId"
            class="fe-editor__area wp-editor-area"
            :aria-labelledby="ariaLabelledby || null"
        >{{ modelValue }}</textarea>

        <template v-else>
            <div v-if="$slots.tools" class="fe-editor__tools">
                <slot name="tools" />
            </div>

            <el-input
                v-model="plainContent"
                type="textarea"
                :rows="6"
                :placeholder="placeholder"
                :aria-labelledby="ariaLabelledby || null"
            />
        </template>

        <Teleport v-if="toolsTarget" :to="toolsTarget">
            <slot name="tools" />
        </Teleport>
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
                toolsTarget: null,
                themeObserver: null,
                emitTimer: null,
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

                // The host page sets these, so the editor body matches its other inputs.
                const style = getComputedStyle(this.$el);
                const token = (name, fallback) => style.getPropertyValue(name).trim() || fallback;
                const text = token('--fe-editor-text', '#f0f3f5');

                return `html,body{background-color:${token('--fe-editor-bg', '#11171d')};color:${text};}`
                    + `*{color:${text};border-color:${token('--fe-editor-border', '#2C3C4E')};}`
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
                        setup: (editor) => {
                            // Each emit serializes the whole document, so emit once typing pauses,
                            // and at once on blur so a Save right after typing gets the last keys.
                            editor.on('change keyup', this.queueEmit);
                            editor.on('blur', this.emitContent);

                            // TinyMCE edits inside an iframe, and the textarea's
                            // association does not reach it. Name the iframe too,
                            // or the Visual tab is an unlabelled control.
                            editor.on('init', () => {
                                // Through the swappable style tag, not content_style, which
                                // init bakes in and a later switch to light cannot undo.
                                this.applyThemeStyle();

                                if (this.ariaLabelledby) {
                                    editor.iframeElement?.setAttribute(
                                        'aria-labelledby', this.ariaLabelledby
                                    );
                                }
                            });
                        },
                    },
                });

                // Code-tab quick tags and media inserts set the value without a change event; blur still fires.
                jQuery(`#${this.editorId}`).on('blur', this.emitContent);

                // wp.editor builds the Visual/Code row outside Vue; the tools slot is teleported in beside the tabs.
                const tabs = document.querySelector(`#wp-${this.editorId}-wrap .wp-editor-tabs`);

                if (this.$slots.tools && tabs) {
                    const tools = document.createElement('div');
                    tools.className = 'fe-editor__tools';
                    tabs.before(tools);
                    this.toolsTarget = tools;
                }
            },
            queueEmit() {
                clearTimeout(this.emitTimer);
                this.emitTimer = setTimeout(this.emitContent, 300);
            },
            emitContent() {
                clearTimeout(this.emitTimer);
                this.$emit(
                    'update:modelValue',
                    this.editor.getContent(this.editorId)
                );
            },
            insertContent(html) {
                const tiny = window.tinymce?.get(this.editorId);

                if (tiny && !tiny.isHidden()) {
                    tiny.insertContent(html);
                } else if (this.hasWpEditor) {
                    const area = document.getElementById(this.editorId);
                    const start = area.selectionStart;

                    area.value = area.value.slice(0, start) + html + area.value.slice(area.selectionEnd);
                } else {
                    this.plainContent += html;
                    return;
                }

                this.emitContent();
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
            clearTimeout(this.emitTimer);

            jQuery(`#${this.editorId}`).off('blur', this.emitContent);
            this.editor.remove(this.editorId);
        },
    };
</script>
