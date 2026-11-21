# collseq: the collections of values in a row, on the keys of the
# harness: a vector takes every key and gives them back, the last first
# (lib/std/vec.aw); a queue with room for 1024 takes each key and, when it
# is full, gives the oldest first (lib/std/queue.aw); a heap takes every key
# and gives them back, the smallest first (lib/std/heap.aw). Each makes its
# collection in the 64 bytes of the harness and gives it back. Without
# memory for it the program ends with the status 1, as the harness does.
use "bench/algo/harness.aw"
use "lib/std/vec.aw"
use "lib/std/queue.aw"
use "lib/std/heap.aw"
fn kvec a n r s
	i=0
	while i<n
		load a+i*8 as x
		call vecpush s x as o
		ifz o
			sys exit 1
		end
		i+=1
	end
	i=0
	while i<n
		call vecpop s as x
		store r+i*8 x
		i+=1
	end
	call vecfree s
end
fn kqueue a n r s
	call queueinit s 1024 as o
	ifz o
		sys exit 1
	end
	j=0
	i=0
	while i<n
		load a+i*8 as x
		call queuepush s x as o
		ifz o
			call queuepop s as y
			store r+j*8 y
			j+=1
			call queuepush s x
		end
		i+=1
	end
	while j<n
		call queuepop s as y
		store r+j*8 y
		j+=1
	end
	call queuefree s
end
fn kheap a n r s
	i=0
	while i<n
		load a+i*8 as x
		call heappush s x as o
		ifz o
			sys exit 1
		end
		i+=1
	end
	i=0
	while i<n
		call heappop s as x
		store r+i*8 x
		i+=1
	end
	call heapfree s
end
ref kvec as a
ref kqueue as b
ref kheap as c
call algocoll a b c
