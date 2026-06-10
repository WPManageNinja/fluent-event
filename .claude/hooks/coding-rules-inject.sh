#!/usr/bin/env bash
#
# PreToolUse(Edit|Write|MultiEdit|NotebookEdit): inject the relevant common coding
# rules from .claude/rules/ into context BEFORE code is written, so the edit follows
# them. Companion to coding-rules-scan.sh (the PostToolUse safety-net scanner).
#
# Emits hookSpecificOutput.additionalContext (same mechanism as git-rules.sh) — the
# harness feeds it to the model automatically. Silent (exit 0, no output) for
# non-code files, files outside the plugin, or when CODING_RULES_SCAN_OFF=1.
#
#   stdin  = PreToolUse JSON (tool_name, tool_input.file_path, ...)
#   stdout = {hookSpecificOutput:{hookEventName, additionalContext}}  OR nothing
#
set -euo pipefail

[ "${CODING_RULES_SCAN_OFF:-0}" = "1" ] && exit 0

input=$(cat)
fp=$(printf '%s' "$input" | jq -r '.tool_input.file_path // ""' 2>/dev/null) || exit 0

[ -z "$fp" ] && exit 0

# Code files only — no point loading rules for an .md/.json edit
case "$fp" in
  *.php|*.vue|*.js|*.ts|*.scss|*.css) : ;;
  *) exit 0 ;;
esac

# Within this plugin only
case "$fp" in
  */fluent-events/*|*/fluent-event/*) : ;;
  *) exit 0 ;;
esac

root="${CLAUDE_PROJECT_DIR:-.}"
rules="$root/.claude/rules"
[ -d "$rules" ] || exit 0

# Universal rules always; area rules by file type (mirror the scanner's scope)
files=("always.md" "fix-patterns.md")
case "$fp" in
  *.php)                  files+=("controllers-services.md" "routes.md") ;;
  *.vue)                  files+=("vue.md" "a11y-i18n.md") ;;
  *.js|*.ts|*.scss|*.css) files+=("vue.md" "a11y-i18n.md") ;;
esac

ctx="Common coding rules for this edit (from .claude/rules/). Follow them while writing this file; a PostToolUse scanner hard-blocks a subset."
for f in "${files[@]}"; do
  if [ -f "$rules/$f" ]; then
    ctx="$ctx"$'\n\n--- .claude/rules/'"$f"$' ---\n'"$(cat "$rules/$f")"
  fi
done

jq -n --arg c "$ctx" \
  '{hookSpecificOutput: {hookEventName: "PreToolUse", additionalContext: $c}}'
