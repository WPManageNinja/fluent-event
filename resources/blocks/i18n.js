// The block editor's strings, translated in PHP (BlockEditorHandler::strings()) and passed in fluentEventBlocks,
// the same way the admin app and the portal get theirs. The text domain argument stays so make-pot still reads it.
export function __(text) {
    return ((window.fluentEventBlocks || {}).i18n || {})[text] || text;
}
