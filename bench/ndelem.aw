use "bench/algo/harness.aw"
use "lib/nd/array.aw"
use "lib/nd/elem.aw"
buf x 176
buf y 176
buf z 176
buf k 176
buf s 8
fn wrap a b r n
	store s n
	call ndwrap x a n*8 1 s
	call ndwrap y b n*8 1 s
	call ndwrap z r n*8 1 s
end
fn add a b r n
	call wrap a b r n
	call ndadd z x y
end
fn mul a b r n
	call wrap a b r n
	call ndmul z x y
end
fn scale a b r n
	if n
		call wrap a b r n
		load b as v
		call ndscalar k v
		call ndmul z x k
	end
end
ref add as p
ref mul as q
ref scale as t
call algonum p q t 0
