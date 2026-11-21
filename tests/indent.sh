#!/bin/sh
# Check that every AIL source of this repository is indented as
# build/tools/indent, the formatter of the main repository, indents it:
# one tab a block. A new or edited source is indented with
#   build/tools/indent file.aw
set -u
cd "$(dirname "$0")/.." || exit 1
mkdir -p build/tests
tmp=$(mktemp -d build/tests/indent.XXXXXX) || exit 1
trap 'rm -rf "$tmp"' EXIT
trap 'exit 1' HUP INT TERM
git ls-files '*.aw' 2> /dev/null > "$tmp/list"
[ -s "$tmp/list" ] || ls tools/*.aw tools/lib/*.aw bench/*.aw bench/*/*.aw tests/*.aw > "$tmp/list"
xargs build/tools/indent --check < "$tmp/list" > "$tmp/said" 2>&1
r=$?
if [ $r -eq 0 ]; then echo "ok   indent_sources"; else head -n 20 "$tmp/said"; echo "FAIL indent_sources"; fi
exit $r
