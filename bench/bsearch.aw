# bsearch: binary search in the n words at a, which rise. searchlower
# of lib/algo/search.aw gives the first index whose word is at least k,
# or n if every word is smaller; search does that for each of n keys.
use "bench/algo/harness.aw"
use "lib/algo/search.aw"
fn search a n q r
	i=0
	while i<n
		load q+i*8 as k
		call searchlower a n k as l
		store r+i*8 l
		i+=1
	end
end
ref search as k
call algosearch k
