#!/bin/sh
# Check the algorithm programs, bench/<name>.aw, against references that share
# nothing with them. A program reads five words (bench/algo/harness.aw):
# seed, count, shape, runs and mode, and writes its input in mode 1, its
# result in mode 2 and a checksum of the result with its times in mode 0.
# For the counts 0, 1, 2, 3 and 1000 in each of the four shapes:
#   a sort     its result must be what sort(1) makes of its input
#   bsearch    for each key the index awk finds by looking at every
#              word from the first: the first that is not smaller
#   hashtab    for each key that was put in the index of its last
#              place among the keys, which awk keeps under the digits
#              of the key, and -1 for each key that was not
#   graph      the two orders that awk reaches the vertices in, with a
#              queue and with a stack of its own
#   an array   program (ndelem, ndreduce, ndmatmul): the sums and
#              products that awk computes of the numbers of the input
#              with loops of its own, each result within 1e-9 of the
#              sizes, for the counts 0, 1, 2, 3 and 1000, or 30 where
#              the count is the side of a square
#   collseq    the keys, the last first; the keys in their order; and
#              the keys as sort(1) orders them: what a vector, a queue
#              and a heap give back. 3000 keys as well, more than the
#              queue has room for
#   collmap    for a map that got each key with its index: how many
#              keys awk counts, the last index awk has of each key, and
#              a 1 for each key where awk meets it first
#   checksum   mode 0 must write the checksum of the result, computed
#              here, and 8 bytes for each of its kernels
# Words are compared as 16 hexadecimal digits, which sort as the numbers
# do. Two runs each, so that a program which does not start again from
# its input is seen. The same is asked of the C programs of bench/ if
# there is a gcc and of the Rust programs if there is a rustc; without
# the compiler that part is skipped.
set -u
cd "$(dirname "$0")/.." || exit 1
mkdir -p build/tests
tmp=$(mktemp -d build/tests/algo.XXXXXX) || exit 1
trap 'rm -rf "$tmp"' EXIT
trap 'exit 1' HUP INT TERM
fail=0

sorts='quicksort mergesort heapsort'

# words n ...: each number as 8 bytes, the lowest first
words() {
    for x; do
        i=0
        while [ $i -lt 64 ]; do
            printf "\\$(printf '%03o' $((x >> i & 255)))"
            i=$((i + 8))
        done
    done
}

# run program count shape mode: what it writes, a word a line. It fails
# if the program does not end with the status 0 within 10 seconds, so
# the right bytes from a program that then fails or hangs do not pass
run() {
    words 7 "$2" "$3" 2 "$4" | timeout --signal=KILL 10 "$1" > "$tmp/raw" || return 1
    od -An -tx8 -w8 -v "$tmp/raw" | tr -d ' '
}

# checksum file: h = h * 31 + word over its lines, as 16 digits
checksum() {
    h=0
    while read -r w; do
        h=$((h * 31 + (0x${w%????????} << 32 | 0x${w#????????})))
    done < "$1"
    printf '%08x%08x\n' $((h >> 32 & 0xffffffff)) $((h & 0xffffffff))
}

# summed program count shape kernels: mode 0 gives the checksum of
# $tmp/res and that many times
summed() {
    run "$1" "$2" "$3" 0 > "$tmp/sum" || return 1
    [ "$(sed -n 1p "$tmp/sum")" = "$(checksum "$tmp/res")" ] &&
        [ "$(wc -l < "$tmp/sum")" -eq $(($4 + 1)) ]
}

# sorted program: a sort
sorted() {
    for s in 0 1 2 3; do
        for n in 0 1 2 3 1000; do
            run "$1" $n $s 1 > "$tmp/in" || return 1
            run "$1" $n $s 2 > "$tmp/res" || return 1
            [ "$(wc -l < "$tmp/in")" -eq $n ] || return 1
            LC_ALL=C sort "$tmp/in" | cmp -s - "$tmp/res" || return 1
            summed "$1" $n $s 1 || return 1
        done
    done
}

# searched program: for each key the first index of a word not below it
searched() {
    for s in 0 3; do
        for n in 0 1 2 3 1000; do
            run "$1" $n $s 1 > "$tmp/in" || return 1
            run "$1" $n $s 2 > "$tmp/res" || return 1
            [ "$(wc -l < "$tmp/in")" -eq $((n * 2)) ] || return 1
            awk -v n=$n 'NR <= n { a[NR - 1] = $0; next }
                { for (i = 0; i < n && a[i] "" < $0 ""; i++); printf "%016x\n", i }' \
                "$tmp/in" | cmp -s - "$tmp/res" || return 1
            head -n $n "$tmp/in" | LC_ALL=C sort -c 2> /dev/null || return 1
            summed "$1" $n $s 1 || return 1
        done
    done
}

# wraps count: whether a probe passes from the last place of the table
# to its first when the first count keys of $tmp/in are put into the
# table of the programs, in their order, and the count keys after them
# are looked up: the table has a power of two of places, at least 16 and
# at least twice the keys, and a key is looked for from the place that
# bits 32 and up of its product with 0x9e3779b97f4a7c15 name, up to
# itself or a free place
wraps() {
    c=16
    while [ $c -lt $(($1 * 2)) ]; do c=$((c * 2)); done
    w=1
    i=0
    for k in $(head -n $(($1 * 2)) "$tmp/in"); do
        k=$((0x${k%????????} << 32 | 0x${k#????????}))
        h=$((k * -7046029254386353131 >> 32 & (c - 1)))
        while eval "o=\${slot$h:-0}"; [ "$o" != 0 ] && [ "$o" != "$k" ]; do
            h=$(((h + 1) & (c - 1)))
            [ $h -ne 0 ] || w=0
        done
        [ $i -ge "$1" ] || eval "slot$h=$k"
        i=$((i + 1))
    done
    for h in $(seq 0 $((c - 1))); do unset "slot$h"; done
    return $w
}

# hashed program: the last index of each key, then -1 for the others. At
# 8 keys of few values and at 261 keys a lookup goes round the end of the
# table, which wraps checks
hashed() {
    for s in 0 3; do
        for n in 0 1 2 3 8 261 1000; do
            run "$1" $n $s 1 > "$tmp/in" || return 1
            run "$1" $n $s 2 > "$tmp/res" || return 1
            [ "$(wc -l < "$tmp/in")" -eq $((n * 2)) ] || return 1
            awk -v n=$n 'NR <= n { k[NR - 1] = $0; last[$0] = NR - 1 }
                END { for (i = 0; i < n; i++) printf "%016x\n", last[k[i]]
                    for (i = 0; i < n; i++) print "ffffffffffffffff" }' \
                "$tmp/in" | cmp -s - "$tmp/res" || return 1
            case $n$s in 83|2610) wraps $n || return 1;; esac
            summed "$1" $n $s 3 || return 1
        done
    done
}

# reached program: the vertices breadth first, then depth first
reached() {
    for s in 0 3; do
        for n in 0 1 2 3 1000; do
            run "$1" $n $s 1 > "$tmp/in" || return 1
            run "$1" $n $s 2 > "$tmp/res" || return 1
            awk -v n=$n 'function hex(s,  i, v) {
                    v = 0
                    for (i = 9; i <= 16; i++)
                        v = v * 16 + index("0123456789abcdef", substr(s, i, 1)) - 1
                    return v
                }
                NR <= n + 1 { off[NR - 1] = hex($0); next }
                { nbr[NR - n - 2] = hex($0) }
                END {
                    t = 0; h = 0
                    for (s = 0; s < n; s++) {
                        if (s in seen) continue
                        seen[s] = 1; q[t++] = s
                        while (h < t) {
                            v = q[h++]
                            for (e = off[v]; e < off[v + 1]; e++) {
                                w = nbr[e]
                                if (!(w in seen)) { seen[w] = 1; q[t++] = w }
                            }
                        }
                    }
                    for (i = 0; i < n; i++) printf "%016x\n", q[i]
                    for (s = 0; s < n; s++) {
                        if (s in done) continue
                        st[0] = s; p = 1
                        while (p) {
                            v = st[--p]
                            if (v in done) continue
                            done[v] = 1; printf "%016x\n", v
                            for (e = off[v]; e < off[v + 1]; e++) st[p++] = nbr[e]
                        }
                    }
                }' "$tmp/in" | cmp -s - "$tmp/res" || return 1
            [ "$(wc -l < "$tmp/res")" -eq $((n * 2)) ] || return 1
            summed "$1" $n $s 2 || return 1
        done
    done
}

# numbered program: kernels on doubles. awk reads the words of the input
# as doubles (sign, exponent and 52 bits), computes what the program of
# that name is to compute, and compares the words of the result
numbered() {
    name=${1##*/}
    kernels=3
    side=30
    [ "$name" = ndelem ] && side=1000
    [ "$name" = ndmatmul ] && kernels=1
    for s in 0 1 2 3; do
        for n in 0 1 2 3 $side; do
            l=$n
            [ "$name" = ndelem ] || l=$((n * n))
            run "$1" $n $s 1 > "$tmp/in" || return 1
            run "$1" $n $s 2 > "$tmp/res" || return 1
            [ "$(wc -l < "$tmp/in")" -eq $((l * 2)) ] || return 1
            [ "$(wc -l < "$tmp/res")" -eq $((l * kernels)) ] || return 1
            cat "$tmp/in" "$tmp/res" | awk -v p="$name" -v n=$n -v l=$l '
                function hex(s,  i, v) {
                    v = 0
                    for (i = 1; i <= length(s); i++)
                        v = v * 16 + index("0123456789abcdef", substr(s, i, 1)) - 1
                    return v
                }
                function dbl(w,  h, e, m) {
                    h = hex(substr(w, 1, 3)); e = h % 2048; m = hex(substr(w, 4))
                    if (e == 0 && m == 0) return 0
                    return (h >= 2048 ? -1 : 1) * (1 + m / 4503599627370496) * 2 ^ (e - 1023)
                }
                NR <= l { a[NR - 1] = dbl($0); next }
                NR <= l * 2 { b[NR - 1 - l] = dbl($0); next }
                { r[NR - 1 - l * 2] = dbl($0) }
                END {
                    if (p == "ndelem")
                        for (i = 0; i < n; i++) {
                            w[i] = a[i] + b[i]; w[n + i] = a[i] * b[i]; w[n * 2 + i] = a[i] * b[0]
                        }
                    if (p == "ndreduce" && n) {
                        for (i = 0; i < l; i++) {
                            w[0] += a[i]; w[l + i % n] += a[i]; w[l * 2] += a[i] * b[i]
                        }
                    }
                    if (p == "ndmatmul")
                        for (i = 0; i < n; i++)
                            for (k = 0; k < n; k++)
                                for (j = 0; j < n; j++)
                                    w[i * n + j] += a[i * n + k] * b[k * n + j]
                    for (i = 0; i < NR - l * 2; i++) {
                        d = w[i] - r[i]; if (d < 0) d = -d
                        x = w[i] < 0 ? -w[i] : w[i]; y = r[i] < 0 ? -r[i] : r[i]
                        if (d > 1e-9 * (x + y)) exit 1
                    }
                }' || return 1
            summed "$1" $n $s $kernels || return 1
        done
    done
}

# collected program: the collections. tac is not everywhere, so the
# reverse is made by awk as well
collected() {
    name=${1##*/}
    for s in 0 1 2 3; do
        for n in 0 1 2 3 1000 3000; do
            run "$1" $n $s 1 > "$tmp/in" || return 1
            run "$1" $n $s 2 > "$tmp/res" || return 1
            [ "$(wc -l < "$tmp/in")" -eq $n ] || return 1
            if [ "$name" = collseq ]; then
                {
                    awk '{ l[NR] = $0 } END { for (i = NR; i; i--) print l[i] }' "$tmp/in"
                    cat "$tmp/in"
                    LC_ALL=C sort "$tmp/in"
                } | cmp -s - "$tmp/res" || return 1
            else
                awk '{ k[NR] = $0; if (!($0 in last)) { first[NR] = 1; keys++ } last[$0] = NR - 1 }
                    END {
                        for (i = 1; i <= NR; i++) printf "%016x\n", i == 1 ? keys : 0
                        for (i = 1; i <= NR; i++) printf "%016x\n", last[k[i]]
                        for (i = 1; i <= NR; i++) printf "%016x\n", first[i] + 0
                    }' "$tmp/in" | cmp -s - "$tmp/res" || return 1
            fi
            summed "$1" $n $s 3 || return 1
        done
    done
}

# each kind program ...: check the programs, and their C and Rust forms
each() {
    kind=$1
    shift
    for p; do
        if $kind "build/$p"
        then echo "ok   algo $p"
        else echo "FAIL algo $p"; fail=1
        fi
        if [ -n "$cc" ]; then
            if gcc -O1 -o "$tmp/$p" "bench/$p.c" 2> /dev/null && $kind "$tmp/$p"
            then echo "ok   algo $p.c"
            else echo "FAIL algo $p.c"; fail=1
            fi
        fi
        [ -n "$rc" ] || continue
        if rustc -O -o "$tmp/rs/$p" "bench/$p.rs" 2> /dev/null && $kind "$tmp/rs/$p"
        then echo "ok   algo $p.rs"
        else echo "FAIL algo $p.rs"; fail=1
        fi
    done
}

cc=$(command -v gcc)
[ -n "$cc" ] || echo "skip algo C programs: no gcc"
rc=$(command -v rustc)
[ -n "$rc" ] || echo "skip algo Rust programs: no rustc"
# a program is known by the name of its file, so the Rust ones are apart
mkdir "$tmp/rs" || exit 1
each sorted $sorts
each searched bsearch
each hashed hashtab
each reached graph
each numbered ndelem ndreduce ndmatmul
each collected collseq collmap

# anything but five whole words is refused
r=0
for p in $sorts bsearch hashtab graph ndelem ndreduce ndmatmul collseq collmap; do
    printf 12345678 | "build/$p" > "$tmp/res"
    [ $? -eq 1 ] && [ ! -s "$tmp/res" ] || r=1
    words 7 268435456 0 1 0 | "build/$p" > "$tmp/res"
    [ $? -eq 1 ] && [ ! -s "$tmp/res" ] || r=1
done
# a square of 2^28 numbers or more as well
for p in ndreduce ndmatmul; do
    words 7 16384 0 1 0 | "build/$p" > "$tmp/res"
    [ $? -eq 1 ] && [ ! -s "$tmp/res" ] || r=1
done
# a collection that gets no memory ends the program with the status 1
# and no result: with 40 MB for a million keys the harness has its
# arrays and the vector cannot grow to the end, with 100 MB the map
# cannot. A subshell, since the limit cannot be raised again
(ulimit -v 40000; words 7 1000000 0 1 2 | build/collseq > "$tmp/res")
[ $? -eq 1 ] && [ ! -s "$tmp/res" ] || r=1
(ulimit -v 100000; words 7 1000000 0 1 2 | build/collmap > "$tmp/res")
[ $? -eq 1 ] && [ ! -s "$tmp/res" ] || r=1
if [ $r -eq 0 ]
then echo "ok   algo refuses"
else echo "FAIL algo refuses"; fail=1
fi

# the time of every kernel is there: more than nothing, since the clock
# counts nanoseconds and a kernel is many, and less than the ten seconds
# a run may take, which a clock that went wrong would not give
r=0
for p in $sorts bsearch hashtab graph ndelem ndreduce ndmatmul collseq collmap; do
    words 7 100 0 3 0 | "build/$p" | od -An -v -tu8 -w8 | awk '
        NR > 1 && ($1 == 0 || $1 >= 10000000000) { bad = 1 }
        END { exit bad || NR < 2 }' || r=1
done
if [ $r -eq 0 ]
then echo "ok   algo clock"
else echo "FAIL algo clock"; fail=1
fi
exit $fail
