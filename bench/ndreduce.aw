use "bench/algo/harness.aw"
use "lib/nd/array.aw"
use "lib/nd/elem.aw"
use "lib/nd/reduce.aw"
buf x 176
buf y 176
buf z 176
buf s 16
fn total a b r n
	if n
		store s n
		store s+8 n
		call ndwrap x a n*n*8 2 s
		call ndsum x as v ok
		store r v
	end
end
fn cols a b r n
	store s n
	store s+8 n
	call ndwrap x a n*n*8 2 s
	call ndwrap z r n*8 1 s
	call ndsumax z x 0
end
fn dot a b r n
	if n
		store s n*n
		call ndwrap x a n*n*8 1 s
		call ndwrap y b n*n*8 1 s
		call nddot x y as v ok
		store r v
	end
end
ref total as p
ref cols as q
ref dot as t
call algonum p q t 1
