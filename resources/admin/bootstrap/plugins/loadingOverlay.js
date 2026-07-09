export default {
    install(app, { alpha = 0.3, lighten = 0.9, cssVar = '--el-bg-color' } = {}) {

        /**
         * Derive a semi-transparent overlay colour from the theme's base
         * CSS variable. Reads `cssVar` (default `--el-bg-color`), lightens
         * it by `lighten`, and applies the configured `alpha`.
         *
         * @returns {string} `rgba(r, g, b, alpha)` or `''` when the CSS var
         *                   can't be resolved to a valid hex triplet.
         */
        function getOverlayColor() {
            const color = app.config.globalProperties.$getCssVar(cssVar);
            
            if (!color) return '';

            const rgb = color.replace('#', '')
                .match(/.{1,2}/g)?.map(x => parseInt(x, 16));
            
            if (!rgb || rgb.length < 3) return '';

            const adjusted = rgb.map(c => 
                Math.min(
                    255, Math.max(
                        0, Math.round(c + (255 - c) * (1 - lighten))
                    )
                )
            );

            return `rgba(${adjusted.join(',')}, ${alpha})`;
        }

        /**
         * Paint a freshly-inserted `.el-loading-mask` with the theme-aware
         * overlay colour — runs inside a MutationObserver so Element Plus's
         * `v-loading` directive gets the theme tint without patching the
         * component.
         *
         * @param {HTMLElement} mask  The loading-mask element to recolour.
         */
        function applyOverlay(mask) {
            if (!mask) return;

            const color = getOverlayColor();

            if (!color) return;
            
            requestAnimationFrame(() => {
                mask.style.backgroundColor = color;
            });
        }

        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node instanceof HTMLElement && node.classList.contains('el-loading-mask')) {
                        applyOverlay(node);
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }
};
