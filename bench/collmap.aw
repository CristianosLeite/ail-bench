# collmap: the hash map of lib/std/map.aw on the keys of the harness, in
# three kernels that share one map: put gives each key its index as
# value and writes how many keys the map then has; get writes the value
# of each key; del takes each key out, writes whether it was there, and
# gives the map back. Without memory for the map the program ends with
# the status 1, as the harness does.
use "bench/algo/harness.aw"
use "lib/std/map.aw"
fn kput a n r s
	i=0
	while i<n
		load a+i*8 as x
		call mapput s x i as o
		ifz o
			sys exit 1
		end
		i+=1
	end
	if n
		load s+16 as c
		store r c
	end
end
fn kget a n r s
	i=0
	while i<n
		load a+i*8 as x
		call mapget s x as v
		store r+i*8 v
		i+=1
	end
end
fn kdel a n r s
	i=0
	while i<n
		load a+i*8 as x
		call mapdel s x as v
		store r+i*8 v
		i+=1
	end
	call mapfree s
end
ref kput as a
ref kget as b
ref kdel as c
call algocoll a b c
