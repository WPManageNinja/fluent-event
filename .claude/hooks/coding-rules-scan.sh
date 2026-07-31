#!/usr/bin/env bash
#
# PostToolUse hook: safety-net scanner for FluentEvent source edits.
#
# Diff-aware: only flags hard-block patterns introduced by THIS edit
# (added lines vs HEAD). Existing legacy callsites with `phpcs:ignore`
# annotations are not re-flagged on unrelated edits.
#
# Rule of record:
#   Common rules  -> .claude/rules/  (always, controllers-services, routes, vue, fix-patterns)
#   Area rulesets -> .claude/rules/  (outbound-http, email, multisite, deps-build)
#
# Contract:
#   stdin  = JSON from Claude Code with tool_name, tool_input{file_path, ...}
#   exit 0 = silent pass
#   exit 2 = stderr fed back to Claude as a "fix this then continue" signal
#
# Per-session disable:  CODING_RULES_SCAN_OFF=1
# Per-file disable:     // coding-rules-scan: off  (anywhere in the file)
# Per-line disable:     same line contains  phpcs:ignore  or  coding-rules-scan:ignore
#
set -uo pipefail

[ "${CODING_RULES_SCAN_OFF:-0}" = "1" ] && exit 0

PAYLOAD=$(cat)

PARSED=$(printf '%s' "$PAYLOAD" | python3 -c '
import json, sys
try:
    d = json.load(sys.stdin)
    tn = d.get("tool_name", "")
    fp = (d.get("tool_input") or {}).get("file_path", "")
    print(tn)
    print(fp)
except Exception:
    print("UNKNOWN")
    print("")
' 2>/dev/null) || exit 0

TOOL_NAME=$(printf '%s\n' "$PARSED" | sed -n '1p')
FILE_PATH=$(printf '%s\n' "$PARSED" | sed -n '2p')

case "$TOOL_NAME" in
  Edit|Write|MultiEdit|NotebookEdit) : ;;
  *) exit 0 ;;
esac

[ -z "$FILE_PATH" ] && exit 0

case "$FILE_PATH" in
  *.md|*.txt|*.json|*.lock) exit 0 ;;
  */builds/*|*/node_modules/*|*/vendor/*|*/.agent/reports/*) exit 0 ;;
  */audit_*) exit 0 ;;
esac

case "$FILE_PATH" in
  *.php|*.vue|*.js|*.ts|*.scss|*.css) : ;;
  *) exit 0 ;;
esac

case "$FILE_PATH" in
  */fluent-events/*|*/fluent-event/*) : ;;
  *) exit 0 ;;
esac

[ -f "$FILE_PATH" ] || exit 0

if grep -qE 'coding-rules-scan:[[:space:]]*off' "$FILE_PATH" 2>/dev/null; then
  exit 0
fi

REPO_ROOT=$(cd "$(dirname "$FILE_PATH")" 2>/dev/null && git rev-parse --show-toplevel 2>/dev/null || true)

SCAN_FILE=$(mktemp -t coding-rules-scan.XXXXXX)
trap 'rm -f "$SCAN_FILE"' EXIT

USE_DIFF=0
if [ -n "$REPO_ROOT" ] && git -C "$REPO_ROOT" ls-files --error-unmatch "$FILE_PATH" >/dev/null 2>&1; then
  git -C "$REPO_ROOT" diff --unified=0 -- "$FILE_PATH" 2>/dev/null | awk '
    /^@@/ {
      match($0, /\+[0-9]+/)
      cur = substr($0, RSTART+1, RLENGTH-1) + 0
      next
    }
    /^\+\+\+/ { next }
    /^\+/ {
      printf "%d:%s\n", cur, substr($0,2)
      cur++
      next
    }
    /^-/ { next }
    { cur++ }
  ' > "$SCAN_FILE"
  if [ -s "$SCAN_FILE" ]; then
    USE_DIFF=1
  else
    if ! git -C "$REPO_ROOT" diff --quiet HEAD -- "$FILE_PATH" 2>/dev/null; then
      USE_DIFF=1
    fi
  fi
fi

if [ "$USE_DIFF" != "1" ]; then
  awk '{printf "%d:%s\n", NR, $0}' "$FILE_PATH" > "$SCAN_FILE"
fi

# Strip per-line opt-out
grep -vE 'phpcs:ignore|coding-rules-scan:ignore' "$SCAN_FILE" > "${SCAN_FILE}.filtered" || true
mv "${SCAN_FILE}.filtered" "$SCAN_FILE"

VIOLATIONS=""
add_violation() {
  VIOLATIONS+="  - $1"$'\n'
}

match_any() {
  local pattern="$1"
  grep -E "^[0-9]+:.*${pattern}" "$SCAN_FILE" 2>/dev/null | head -1 | cut -d: -f1
}

match_word_left() {
  local pattern="$1"
  grep -E "^[0-9]+:(.*[^a-zA-Z_])?${pattern}" "$SCAN_FILE" 2>/dev/null | head -1 | cut -d: -f1
}

# 1. Wholesale superglobal assignment
LN=$(match_any '\$[A-Za-z_][A-Za-z0-9_]*[[:space:]]*=[[:space:]]*\$_(POST|GET|REQUEST|COOKIE)[[:space:]]*;')
[ -n "$LN" ] && add_violation "L$LN: wholesale superglobal assignment. Extract each field with sanitize_text_field(wp_unslash(\$_POST['x'] ?? '')). Rule: .claude/rules/always.md (Input handling)."

# 2. ->fill($request->all())
LN=$(match_any '->fill[[:space:]]*\([[:space:]]*\$request->all[[:space:]]*\(')
[ -n "$LN" ] && add_violation "L$LN: ->fill(\$request->all()) — mass-assignment surface. Extract validated fields. Rule: .claude/rules/controllers-services.md."

# 3. Model::create($_POST / $request->all())
LN=$(match_any '::create[[:space:]]*\([[:space:]]*\$_(POST|GET|REQUEST)')
[ -z "$LN" ] && LN=$(match_any '::create[[:space:]]*\([[:space:]]*\$request->all[[:space:]]*\(')
[ -n "$LN" ] && add_violation "L$LN: Model::create() with raw request payload. Extract validated fields. Rule: .claude/rules/controllers-services.md."

# 4. permission_callback => '__return_true'
LN=$(match_any "permission_callback['\"]?[[:space:]]*=>[[:space:]]*['\"]?__return_true")
[ -n "$LN" ] && add_violation "L$LN: public REST route (permission_callback => '__return_true'). Verify ownership + rate-limit + resource-bound nonce. Rule: .claude/rules/routes.md."

# 5. unserialize() / maybe_unserialize() — only a sanctioned safeUnserialize is allowed
LN=$(grep -E "^[0-9]+:(.*[^a-zA-Z_])?(maybe_)?unserialize[[:space:]]*\(" "$SCAN_FILE" 2>/dev/null | grep -v 'safeUnserialize' | head -1 | cut -d: -f1)
[ -n "$LN" ] && add_violation "L$LN: unserialize()/maybe_unserialize() — object injection risk. Use a sanctioned safeUnserialize() helper or json_decode(). Rule: .claude/rules/fix-patterns.md (Object injection)."

# 6. eval() / new Function()
LN=$(match_word_left 'eval[[:space:]]*\(')
[ -n "$LN" ] && add_violation "L$LN: eval() — banned. Refactor to data-driven dispatch. Rule: .claude/rules/fix-patterns.md (eval / new Function)."
LN=$(match_any 'new[[:space:]]+Function[[:space:]]*\(')
[ -n "$LN" ] && add_violation "L$LN: new Function() — banned. Use a registered handler map. Rule: .claude/rules/fix-patterns.md (eval / new Function)."

# 7. v-html on .vue
case "$FILE_PATH" in
  *.vue)
    LN=$(match_any 'v-html[[:space:]]*=')
    [ -n "$LN" ] && add_violation "L$LN: v-html introduced — verify input sanitized SERVER-SIDE (wp_kses_post before API response). Rule: .claude/rules/vue.md."
    ;;
esac

# 8. Composition API <script setup> introduction (Options API only)
case "$FILE_PATH" in
  *.vue)
    LN=$(match_any '<script[[:space:]]+setup')
    [ -n "$LN" ] && add_violation "L$LN: <script setup> (Composition API) introduced. Options API only — convert to export default { ... }. Rule: .claude/rules/vue.md."
    ;;
esac

# 9. wp_remote_* without 'timeout' anywhere in file
if grep -qE 'wp_remote_(get|post|request|head)[[:space:]]*\(' "$FILE_PATH" 2>/dev/null; then
  if ! grep -q 'timeout' "$FILE_PATH" 2>/dev/null; then
    LN=$(grep -nE 'wp_remote_(get|post|request|head)[[:space:]]*\(' "$FILE_PATH" 2>/dev/null | head -1 | cut -d: -f1)
    add_violation "L$LN: wp_remote_* without 'timeout' arg in file. Set timeout + redirection bounds + SSRF private-IP rejection (prefer wp_safe_remote_*). Rule: .claude/rules/outbound-http.md."
  fi
fi

# 10. mt_rand / uniqid alongside token/secret/nonce variable (DIFF lines only)
if [ "$USE_DIFF" = "1" ]; then
  LN=$(match_word_left '(mt_rand|uniqid)[[:space:]]*\(')
  if [ -n "$LN" ]; then
    if grep -qE '\$(token|secret|nonce|key|hash|salt|otp|code|api_key|webhook_key)\b' "$SCAN_FILE" 2>/dev/null; then
      add_violation "L$LN: mt_rand/uniqid alongside token/secret/nonce var. Use wp_generate_password(32, false, false) or bin2hex(random_bytes(16)). Rule: .claude/rules/fix-patterns.md (Weak randomness)."
    fi
  fi
fi

# 11. hash_hmac without hash_equals (whole-file context)
if grep -qE 'hash_hmac[[:space:]]*\(' "$FILE_PATH" 2>/dev/null; then
  if ! grep -q 'hash_equals' "$FILE_PATH" 2>/dev/null; then
    LN=$(grep -nE 'hash_hmac[[:space:]]*\(' "$FILE_PATH" 2>/dev/null | head -1 | cut -d: -f1)
    add_violation "L$LN: hash_hmac without hash_equals — non-constant-time compare risk. hash_equals(\$expected, \$received). Rule: .claude/rules/fix-patterns.md (Constant-time compare)."
  fi
fi

# 12. switch_to_blog without restore_current_blog (whole-file)
if grep -q 'switch_to_blog' "$FILE_PATH" 2>/dev/null; then
  if ! grep -q 'restore_current_blog' "$FILE_PATH" 2>/dev/null; then
    LN=$(grep -nE 'switch_to_blog[[:space:]]*\(' "$FILE_PATH" 2>/dev/null | head -1 | cut -d: -f1)
    add_violation "L$LN: switch_to_blog without restore_current_blog — leaks blog context. Pair in try/finally. Rule: .claude/rules/multisite.md."
  fi
fi

# 13. wp_mail header build without CR/LF strip (whole-file)
if grep -qE "['\"](Reply-To|From|Cc|Bcc|To):" "$FILE_PATH" 2>/dev/null; then
  if ! grep -qE "str_replace[[:space:]]*\(\s*\[?[[:space:]]*[\"']\\\\r" "$FILE_PATH" 2>/dev/null; then
    LN=$(grep -nE "['\"](Reply-To|From|Cc|Bcc|To):" "$FILE_PATH" 2>/dev/null | head -1 | cut -d: -f1)
    add_violation "L$LN: email header build present but no CR/LF strip. Header injection risk. str_replace([\"\\r\", \"\\n\"], '', \$value). Rule: .claude/rules/email.md."
  fi
fi

# 14. time() used as enqueue script version (cache-bust antipattern)
LN=$(match_any 'wp_(enqueue|register)_(script|style)[[:space:]]*\([^)]*,[[:space:]]*time[[:space:]]*\(')
[ -n "$LN" ] && add_violation "L$LN: time() used as asset version — defeats browser caching. Use FLUENT_EVENT_PLUGIN_VERSION. Rule: .claude/rules/deps-build.md."

# 15. postMessage with target origin '*' (Vue) — diff-aware
case "$FILE_PATH" in
  *.vue|*.js)
    if [ "$USE_DIFF" = "1" ]; then
      LN=$(match_any "postMessage[[:space:]]*\([^)]*,[[:space:]]*['\"]\\*['\"]")
      [ -n "$LN" ] && add_violation "L$LN: postMessage target origin '*' — leaks message to any frame. Use a specific origin. Rule: .claude/rules/vue.md (postMessage origin)."
    fi
    ;;
esac

if [ -n "$VIOLATIONS" ]; then
  {
    if [ "$USE_DIFF" = "1" ]; then
      echo "coding-rules safety net: NEW hard-block patterns in this edit to $FILE_PATH"
    else
      echo "coding-rules safety net: hard-block patterns in $FILE_PATH"
    fi
    echo ""
    printf '%s' "$VIOLATIONS"
    echo ""
    echo "Fix each violation before continuing."
    echo "Rules:  .claude/rules/"
    echo "Per-line opt-out:  add  phpcs:ignore  or  coding-rules-scan:ignore  to the same line"
    echo "Per-file opt-out:  add a comment containing  coding-rules-scan: off"
    echo "Per-session opt-out:  export CODING_RULES_SCAN_OFF=1"
  } >&2
  exit 2
fi

exit 0
