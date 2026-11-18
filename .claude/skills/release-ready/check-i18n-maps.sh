#!/usr/bin/env bash
#
# Lists the portal and block strings that have no key in their PHP translation map.
# A missing key stays English in every locale. No test covers these two maps; the admin map
# has AdminTranslationMapLintTest.
#
#   bash .claude/skills/release-ready/check-i18n-maps.sh      (from the plugin root)
#
# Exit 0 = every key is mapped. Exit 1 = keys are missing (listed per map).

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../../.."

# Keys passed to <call>('…') in the frontend sources, with \' unescaped.
used_keys() { # <source dir> <call name>
    find "$1" -type f \( -name '*.js' -o -name '*.jsx' -o -name '*.vue' \) -print0 \
        | xargs -0 env CALL="$2" perl -0ne '
            while (/(?<!\w)\Q$ENV{CALL}\E\(\s*\x27((?:[^\x27\\]|\\.)*)\x27/g) {
                (my $k = $1) =~ s/\\(.)/$1/g; print "$k\n";
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

status=0
check() { # <label> <source dir> <call name> <php map file>
    local missing
    missing="$(comm -23 <(used_keys "$2" "$3") <(map_keys "$4"))"
    if [ -n "$missing" ]; then
        echo "Missing from $4 ($1):"
        printf '  %s\n' "$missing"
        status=1
    else
        echo "$1: every key is in $4"
    fi
}

check portal resources/fcom-portal t Modules/FluentCommunity/PortalHandler.php
check blocks resources/blocks __ app/Hooks/Handlers/BlockEditorHandler.php
exit "$status"
