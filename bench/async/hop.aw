# bench/async/hop.aw -- what it costs to hand work to another thread
# and get the result back:  hop <count> [at once]
# Awaits asyncblock of a function that does nothing, <count> times one
# after the other, so the polling thread sleeps for each; with a second
# argument that many pieces at once, all given out before the first is
# awaited. One line: the count, milliseconds, nanoseconds each.
use "lib/std/io.aw"
use "lib/std/task.aw"
use "lib/async/task.aw"
use "lib/async/time.aw"
use "lib/async/io.aw"
use "lib/async/pool.aw"
use "bench/async/num.aw"
fn nothing a b c d
end
call argv 1 as p n
call decnum p n as count
call argv 2 as p n
ref nothing as f
call asyncclock as t0
if n
	alloc count*8 as hs
	i=0
	while i<count
		call asyncblock f 0 0 0 0 as h
		store hs+i*8 h
		i+=1
	end
	i=0
	while i<count
		load hs+i*8 as h
		await h
		i+=1
	end
else
	i=0
	while i<count
		await asyncblock f 0 0 0 0
		i+=1
	end
end
call asyncclock as t1
call putn count
call print " "
call putn (t1-t0)/1000000
call print " "
call putn (t1-t0)/count
call print "\n"
