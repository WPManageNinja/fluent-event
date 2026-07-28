#!/usr/bin/env bash
# PreToolUse(Bash) — before any git/gh command, inject this repo's git rules so
# the model follows them (one concern per commit, never force-push main, stage
# explicit paths, run `npm run test` before commit, ask before push/PR).
#
# Reads the PreToolUse JSON on stdin; emits nothing for non-git commands so the
# tool runs untouched. For git/gh, emits hookSpecificOutput.additionalContext
# carrying the commit-push-pr skill so it lands in the model's context before the
# command.
set -euo pipefail

input=$(cat)
cmd=$(printf '%s' "$input" | jq -r '.tool_input.command // ""')

# Match git / gh as a standalone token at line start or after a shell separator
# (covers compound commands like `cd x && git push`). Avoids false hits on
# words such as "github" or "lengther".
if ! printf '%s' "$cmd" | grep -Eq '(^|[[:space:];&|(])(git|gh)([[:space:]]|$)'; then
  exit 0
fi

root="${CLAUDE_PROJECT_DIR:-.}"
skill="$root/.claude/skills/commit-push-pr/SKILL.md"
header="git/gh command detected — follow this repo's git rules before running it. Key non-negotiables: do nothing destructive without an explicit ask, one concern per commit, stage explicit paths (never \`git add -A\`), run \`npm run test\` before committing, never force-push main, ask before push/PR."

if [ -f "$skill" ]; then
  ctx="$header"$'\n\n--- .claude/skills/commit-push-pr/SKILL.md ---\n'"$(cat "$skill")"
else
  ctx="$header"
fi

jq -n --arg c "$ctx" \
  '{hookSpecificOutput: {hookEventName: "PreToolUse", additionalContext: $c}}'
