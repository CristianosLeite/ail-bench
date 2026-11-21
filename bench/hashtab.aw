# hashtab: a hash table with open addressing, lib/algo/hash.aw. A place
# is 16 bytes, a key and its value, and the key 0 is a place that is
# free; hashfill gives each key its index as value, and hashfind looks
# keys up, those that are there and those that are not.
use "bench/algo/harness.aw"
use "lib/algo/hash.aw"
ref hashfill as a
ref hashfind as b
call algohash a b b
