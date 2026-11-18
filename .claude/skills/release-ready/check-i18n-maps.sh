#!/usr/bin/env bash
#
# Lists the portal and block strings that have no key in their PHP translation map.
# A missing key stays English in every locale. No test covers these two maps; the admin map
# has AdminTranslationMapLintTest.
#
#   bash .claude/skills/release-ready/check-i18n-maps.sh              (from anywhere)
#   bash .claude/skills/release-ready/check-i18n-maps.sh --self-test  (checks this script)
#
# Exit 0 = every key is mapped. Exit 1 = keys are missing (listed per map).
#
# Keys in single or double quotes are read. A template literal (t(`…`)) can't be matched to a
# map key, so it is reported as an error too: write the key as a plain string.

set -euo pipefail

# Keys passed to <call>('…') or <call>("…") in the frontend sources, unescaped. A template
# literal is printed as "TEMPLATE-LITERAL: <file>" so the caller reports it.
used_keys() { # <source dir> <call name>
    find "$1" -type f \( -name '*.js' -o -name '*.jsx' -o -name '*.vue' \) -print0 \
        | xargs -0 env CALL="$2" perl -0ne '
            while (/(?<!\w)\Q$ENV{CALL}\E\(\s*(?:(["\x27])((?:(?!\1)[^\\]|\\.)*)\1|(`))/g) {
                if (defined $3) { print "TEMPLATE-LITERAL: $ARGV\n"; next; }
                (my $k = $2) =~ s/\\(.)/$1/g; print "$k\n";
            }' \
        | sort -u
}

# Keys of the PHP map: lines like  'Key' => __('Key', 'fluent-event'),
map_keys() { # <php file>
    perl -ne '
        if (/^\s*(["\x27])((?:(?!\1)[^\\]|\\.)*)\1\s*=>\s*(?:__|_x|_n|esc_html__)\(/) {
            (my $k = $2) =~ s/\\(.)/$1/g; print "$k\n";
        }' "$1" | sort -u
}

# Prints the missing keys for one map. Returns 1 when any key is missing.
check() { # <label> <source dir> <call name> <php map file>
    local missing
    missing="$(comm -23 <(used_keys "$2" "$3") <(map_keys "$4"))"
    if [ -n "$missing" ]; then
        echo "Missing from $4 ($1):"
        printf '%s\n' "$missing" | sed 's/^/  /'
        return 1
    fi
    echo "$1: every key is in $4"
}

self_test() {
    local dir rc
    dir="$(mktemp -d)"
    trap 'rm -rf "$dir"' RETURN
    mkdir -p "$dir/src"
    cat > "$dir/map.php" <<'PHP'
<?php return [
    'Mapped single' => __('Mapped single', 'fluent-event'),
    "Mapped double" => __("Mapped double", 'fluent-event'),
    'It\'s mapped' => __('It\'s mapped', 'fluent-event'),
];
PHP
    expect() { # <name> <expected rc> <js source>
        printf '%s\n' "$3" > "$dir/src/probe.js"
        rc=0; check probe "$dir/src" t "$dir/map.php" >/dev/null || rc=$?
        if [ "$rc" != "$2" ]; then echo "FAIL: $1 (exit $rc, expected $2)"; return 1; fi
        echo "ok: $1"
    }
    expect "mapped keys pass"            0 "t('Mapped single'); t(\"Mapped double\"); t('It\\'s mapped');"
    expect "unmapped single quote fails" 1 "t('Unmapped single');"
    expect "unmapped double quote fails" 1 "t(\"Unmapped double\");"
    expect "template literal fails"      1 't(`Mapped single`);'
    expect "other calls are ignored"     0 "format('Not a key'); this.t('Mapped single');"
}

if [ "${1:-}" = "--self-test" ]; then
    self_test
    exit
fi

cd "$(dirname "${BASH_SOURCE[0]}")/../../.."
status=0
check portal resources/fcom-portal t Modules/FluentCommunity/PortalHandler.php || status=1
check blocks resources/blocks __ app/Hooks/Handlers/BlockEditorHandler.php || status=1
exit "$status"
