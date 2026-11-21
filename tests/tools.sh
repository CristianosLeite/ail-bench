#!/bin/sh
# Check the tools of this repository, tools/<name>.aw, built as
# build/tools/<name> by make tools.
# - The prerequisites that the Makefile gives a tool must be its source and
#   every file that source reaches with use, no more and no less: an edit
#   of a shared file must build its callers again.
# - perf needs gcc and rustc to measure, so only its refusals are checked:
#   a program it does not know and a CPU that is no number.
# - nptime needs python3 with numpy, so only its refusal of arguments is.
# par is checked by tests/par.sh and tokens by tests/tokens.sh.
set -u
cd "$(dirname "$0")/.." || exit 1
mkdir -p build/tests
tmp=$(mktemp -d build/tests/tools.XXXXXX) || exit 1
trap 'rm -rf "$tmp"' EXIT
trap 'exit 1' HUP INT TERM
fail=0
say() {
    if [ "$2" -eq 0 ]; then echo "ok   $1"; else echo "FAIL $1"; fail=1; fi
}

# the files a source reaches with use, itself included, one a line
closure() {
    todo=$1
    seen=
    while [ -n "$todo" ]; do
        set -- $todo
        [ $# -gt 0 ] || break
        f=$1
        shift
        todo=$*
        case " $seen " in *" $f "*) continue ;; esac
        seen="$seen $f"
        [ -f "$f" ] || continue
        todo="$todo $(sed -n 's/^use "\(.*\)".*/\1/p' "$f" | tr '\n' ' ')"
    done
    printf '%s\n' $seen | sort
}

# the recipe of build/ailc names make by itself, so -n does not run the
# make of the main repository, which would print its rules too
make -pn tools 2> /dev/null > "$tmp/db"
for src in tools/*.aw; do
    [ -f "$src" ] || continue
    name=$(basename "$src" .aw)
    closure "$src" > "$tmp/uses"
    sed -n "s|^build/tools/$name: ||p" "$tmp/db" | tr ' ' '\n' |
        grep -v -e '^build/ailc$' -e '^$' | sort > "$tmp/deps"
    cmp -s "$tmp/uses" "$tmp/deps"
    say "tools_deps $name" $?
done

r=0
build/tools/perf no-such-program > /dev/null 2> "$tmp/said"
[ $? -eq 1 ] && [ "$(cat "$tmp/said")" = "perf: unknown program no-such-program" ] || r=1
build/tools/perf --cpu two > /dev/null 2> "$tmp/said"
[ $? -eq 1 ] && [ "$(cat "$tmp/said")" = "perf: usage: perf [--cpu N] [--quick] [--qsort] [--places] [name ...]" ] || r=1
say tools_perf_errors $r

r=0
build/tools/nptime --cpu > /dev/null 2> "$tmp/said"
[ $? -eq 1 ] && [ "$(cat "$tmp/said")" = "nptime: usage: nptime [--cpu N]" ] || r=1
say tools_nptime_errors $r

exit $fail
