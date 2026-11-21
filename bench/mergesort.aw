# mergesort: sort the n words at a with sortmerge of lib/algo/sort.aw,
# which works in the n words at t that the harness gives.
use "bench/algo/harness.aw"
use "lib/algo/sort.aw"
ref sortmerge as k
call algosort k
