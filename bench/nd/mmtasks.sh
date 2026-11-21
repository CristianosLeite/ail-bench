#!/bin/sh
# bench/nd/mmtasks.sh -- the table of docs/performance.md on the number
# of tasks of a matrix product: bench/nd/mmtasks.aw for several shapes,
# each with one thread and task and with more of both. Run it from the
# top of the repository after make; it needs no other tool. A time is
# the median of five processes, in microseconds; beside the tasks asked
# for are the row blocks that ndruns plans for them, the runs of rows
# ndmatmulp computes as one piece each: one block is computed by the
# caller with no task, and so is a block whose spawn fails, so the
# number is what was planned and not a count of tasks that ran.
set -eu
t=$(mktemp -d)
trap 'rm -rf "$t"' EXIT
build/ailc < bench/nd/mmtasks.aw > "$t/mm"
chmod +x "$t/mm"
word() {
    for v; do
        printf "$(printf '\\%03o' $((v & 255)) $((v >> 8 & 255)) \
            $((v >> 16 & 255)) $((v >> 24 & 255)) 0 0 0 0)"
    done
}
# one n m l threads tasks calls: the row blocks and the three medians
one() {
    word "$@" > "$t/in"
    : > "$t/out"
    for i in 1 2 3 4 5; do "$t/mm" < "$t/in" >> "$t/out"; done
    awk '$4 != 0 { bad = 1 }
        NR == 1 { blocks = $5 }
        { for (k = 1; k <= 3; k++) a[k, NR] = $k }
        END {
            if (bad) { print "differ"; exit 1 }
            printf " %s |", blocks
            for (k = 1; k <= 3; k++) {
                for (i = 1; i <= NR; i++) v[i] = a[k, i]
                for (i = 1; i <= NR; i++) for (j = i + 1; j <= NR; j++)
                    if (v[j] < v[i]) { x = v[i]; v[i] = v[j]; v[j] = x }
                printf " %.0f |", v[3] / 1000
            }
            print ""
        }' "$t/out"
}
echo "| rows x columns x columns, calls | threads, tasks asked | row blocks | one thread | first call | later calls |"
echo "|---|---|---:|---:|---:|---:|"
while read -r n m l c; do
    for p in "1 1" "2 2" "4 4" "8 8" "14 14" "20 20" "4 16" "8 32" "20 64"; do
        set -- $p
        printf '| %s x %s x %s, %s | %s, %s |' "$n" "$m" "$l" "$c" "$1" "$2"
        one "$n" "$m" "$l" "$1" "$2" "$c"
    done
done <<LIST
30 30 30 200
100 100 100 20
300 300 300 5
1000 1000 1000 2
4000 16 16 20
16 250 250 20
250 250 16 20
LIST
