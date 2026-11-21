#!/bin/sh
# Check that every C, Rust and assembly program in bench/ behaves like
# the AIL program it mirrors, an example of the main repository or
# bench/<name>.aw:
# same output and exit status for the same input: none, three bytes and
# eight bytes, which is what the computing programs read. A program need
# not have an assembly equivalent. Needs gcc and rustc
# besides as and ld, so it is not part of `make test`.
set -u
cd "$(dirname "$0")/.."
make -s all >/dev/null || exit 1
t=$(mktemp -d build/bench.XXXXXX) || exit 1
trap 'rm -rf "$t"' EXIT
f=0
for src in ail/examples/*.aw bench/*.aw; do
    n=$(basename "$src" .aw)
    langs='c rs'
    gcc -O1 -w -o "$t/$n.c.bin" "bench/$n.c" &&
        rustc -O -o "$t/$n.rs.bin" "bench/$n.rs" 2>/dev/null ||
        { echo "FAIL $n: build"; f=1; continue; }
    if [ -e "bench/$n.s" ]; then
        langs='c rs s'
        as -o "$t/$n.o" "bench/$n.s" && ld -o "$t/$n.s.bin" "$t/$n.o" ||
            { echo "FAIL $n: build"; f=1; continue; }
    fi
    for input in '' 'hi\n' '12345678'; do
        printf "$input" | "build/$n" > "$t/ref" 2>/dev/null; r=$?
        for l in $langs; do
            printf "$input" | "$t/$n.$l.bin" > "$t/got" 2>/dev/null; g=$?
            if cmp -s "$t/ref" "$t/got" && [ "$r" = "$g" ]; then
                echo "ok   $n.$l"
            else
                echo "FAIL $n.$l"; f=1
            fi
        done
    done
done
exit $f
