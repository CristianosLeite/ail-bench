use "bench/algo/harness.aw"
use "lib/nd/array.aw"
use "lib/nd/elem.aw"
use "lib/nd/reduce.aw"
buf x 176
buf y 176
buf z 176
buf s 16
fn matmul a b r n
	store s n
	store s+8 n
	call ndwrap x a n*n*8 2 s
	call ndwrap y b n*n*8 2 s
	call ndwrap z r n*n*8 2 s
	call ndmatmul z x y
end
ref matmul as p
call algonum p 0 0 1
