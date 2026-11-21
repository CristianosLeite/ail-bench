#!/bin/sh
# Check tools/tokens.aw, built as build/tools/tokens, and the tokenizer
# tools/lib/o200k.aw behind it.
# - tests/tokens/<name>.txt are texts at the edges of the rules (words
#   and contractions, numbers, white space, punctuation, letters and marks
#   of many scripts, long runs), and <name>.ids are the numbers of their
#   tokens as tiktoken 0.14.0 gives them for o200k_base, one a line. The
#   tool must print the same numbers.
# - The count of a file without its comments must be that of the code.
# - Bytes that are no UTF-8, a file that is not there and a call without
#   its arguments are refused with a message and the status 1.
# The tokenizer reads the two files that tools/pins/MANIFEST pins. They
# are fetched once, by tools/pin.sh, and are not in the repository;
# without them nothing is checked, and the script says so and succeeds,
# so that make test needs no network.
set -u
cd "$(dirname "$0")/.." || exit 1
for f in o200k_base.tiktoken UnicodeData.txt; do
    if [ ! -f tools/pins/$f ]; then
        echo "skip tokens (no tools/pins/$f; fetch it with tools/pin.sh $f)"
        exit 0
    fi
done
mkdir -p build/tests
tmp=$(mktemp -d build/tests/tokens.XXXXXX) || exit 1
trap 'rm -rf "$tmp"' EXIT
trap 'exit 1' HUP INT TERM
fail=0
say() {
    if [ "$2" -eq 0 ]; then echo "ok   $1"; else echo "FAIL $1"; fail=1; fi
}

for ids in tests/tokens/*.ids; do
    name=$(basename "$ids" .ids)
    timeout --signal=KILL 10 build/tools/tokens --ids tests/tokens/$name.txt > "$tmp/ids" 2> /dev/null &&
        cmp -s "$tmp/ids" "$ids"
    say "tokens_ids $name" $?
done

# the code of a file: 9 lines of tests/tokens/code.txt, of which one has
# a comment, are the same tokens with the comment and blank lines gone
printf '\n# a comment alone\nx=a+b*c\n   \nstore p+8 n/10   # the rest\n' > "$tmp/with.aw"
printf 'x=a+b*c\nstore p+8 n/10\n' > "$tmp/without.aw"
r=0
a=$(build/tools/tokens -c '#' "$tmp/with.aw" 2> /dev/null) || r=1
b=$(build/tools/tokens --ids "$tmp/without.aw" 2> /dev/null | wc -l) || r=1
[ "$a" = "$b $tmp/with.aw" ] || r=1
build/tools/tokens --code '#' "$tmp/with.aw" > "$tmp/code.aw" 2> /dev/null &&
    cmp -s "$tmp/code.aw" "$tmp/without.aw" || r=1
build/tools/tokens --code '#' > /dev/null 2> "$tmp/said"
[ $? -eq 1 ] && [ "$(cat "$tmp/said")" = "tokens: usage: tokens --code <mark> file" ] || r=1
say tokens_counts_code $r

r=0
build/tools/tokens --ids tests/tokens/bad.bin > /dev/null 2> "$tmp/said"
[ $? -eq 1 ] && [ "$(cat "$tmp/said")" = "tokens: tests/tokens/bad.bin is not UTF-8" ] || r=1
build/tools/tokens --ids "$tmp/none" > /dev/null 2> "$tmp/said"
[ $? -eq 1 ] && [ "$(cat "$tmp/said")" = "tokens: cannot read $tmp/none" ] || r=1
build/tools/tokens -c > /dev/null 2> "$tmp/said"
[ $? -eq 1 ] && [ "$(cat "$tmp/said")" = "tokens: usage: tokens -c <mark> file ..." ] || r=1
say tokens_errors $r

exit $fail
