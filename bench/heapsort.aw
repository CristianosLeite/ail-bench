# heapsort: sort words in place with sortheap of lib/algo/sort.aw, which
# takes the array and the count and leaves the work array alone.
use "bench/algo/harness.aw"
use "lib/algo/sort.aw"
ref sortheap as k
call algosort k
