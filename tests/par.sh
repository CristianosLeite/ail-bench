#!/bin/sh
# Check that the task programs of bench/par print what their sequential
# forms print, at several numbers of threads and tasks: tools/par.aw,
# built as build/tools/par, with --check. Then check the tool itself in a
# directory of its own, where the program with tasks prints something
# else: it must say which pairs of threads and tasks fail, all six, and
# end with the status 1.
set -u
cd "$(dirname "$0")/.." || exit 1
top=$PWD
build/tools/par --check
fail=$?
mkdir -p build/tests
tmp=$(mktemp -d build/tests/par.XXXXXX) || exit 1
trap 'rm -rf "$tmp"' EXIT
trap 'exit 1' HUP INT TERM
mkdir -p "$tmp/bench/par" "$tmp/build"
ln -s "$top/lib" "$tmp/lib"
ln -s "$top/build/ailc" "$tmp/build/ailc"
cp bench/par/collatz_seq.aw "$tmp/bench/par/"
printf 'use "lib/std/io.aw"\ncall print "other\\n"\n' > "$tmp/bench/par/collatz_par.aw"
(cd "$tmp" && "$top/build/tools/par" --check collatz > out 2> /dev/null; echo $? > code)
if [ "$(cat "$tmp/code")" = 1 ] && [ "$(sed -n 1p "$tmp/out")" = "FAIL par collatz" ] &&
    [ "$(grep -c 'threads, .* tasks: not the sequential output$' "$tmp/out")" = 6 ] &&
    [ "$(sed -n 6p "$tmp/out")" = "     20 threads, 1000 tasks: not the sequential output" ]
then echo "ok   par reports_failures"
else echo "FAIL par reports_failures"; fail=1
fi
exit $fail
