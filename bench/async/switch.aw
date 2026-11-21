# bench/async/switch.aw -- what it costs a task to wait and go on:
#   switch <tasks> <waits>
# Every task awaits asyncyield <waits> times, so each wait is a task
# made, two polls and a record given back. One line: the waits in all,
# milliseconds, and nanoseconds a wait, 0 where there is none.
use "lib/std/io.aw"
use "lib/async/task.aw"
use "lib/async/time.aw"
use "bench/async/num.aw"
async fn spin n
	while n
		await asyncyield
		n-=1
	end
end
call argv 1 as p n
call decnum p n as tasks
call argv 2 as p n
call decnum p n as waits
alloc tasks*8 as hs
call asyncclock as t0
i=0
while i<tasks
	call spin waits as h
	store hs+i*8 h
	i+=1
end
i=0
while i<tasks
	load hs+i*8 as h
	await h
	i+=1
end
call asyncclock as t1
n=tasks*waits
call putn n
call print " "
call putn (t1-t0)/1000000
call print " "
# no wait, no time a wait
p=0
if n
	p=(t1-t0)/n
end
call putn p
call print "\n"
