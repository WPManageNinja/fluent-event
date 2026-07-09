import {
	getCssVar as _getCssVar,
	toAlpha as _toAlpha
} from '@/bootstrap/plugins/cssVar';

/**
 * Read the current computed value of a CSS custom property from the document
 * root. Thin composable wrapper around the `cssVar` plugin so components can
 * grab theme tokens (e.g. `--el-color-primary`) without touching `window`.
 *
 * @param  {string} varName  CSS variable name, with or without leading `--`.
 * @returns {string}         Trimmed computed value, or an empty string when
 *                           unset.
 */
export function getCssVar(varName) {
    return _getCssVar(varName);
}

/**
 * Convert a hex color (`#RRGGBB` or `#RGB`) to an `rgba()` string at the
 * given alpha — used to derive hover/focus surfaces from theme tokens.
 *
 * @param  {string} hex    Source color.
 * @param  {number} [alpha=0.2]  Opacity 0–1.
 * @returns {string}       `rgba(r, g, b, a)`.
 */
export function toAlpha(hex, alpha = 0.2) {
	return _toAlpha(hex, alpha);
}

