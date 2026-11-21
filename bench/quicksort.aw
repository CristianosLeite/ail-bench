# quicksort: sort words with sortquick of lib/algo/sort.aw, which sorts
# from an address up to another, in place.
use "bench/algo/harness.aw"
use "lib/algo/sort.aw"
fn sort a n t
	tail sortquick a a+n*8
end
ref sort as k
call algosort k
