# bench/async/calc.aw -- a task that computes between its waits:
#   calc <tasks> <waits> <steps>
# Every task awaits asyncyield <waits> times and between two waits runs a
# loop of <steps> rounds of arithmetic on variables that no wait falls
# into, which the compiler keeps in registers; the sum goes across the
# waits and lives in the record of the task. One line: the sum of the
# results, the rounds in all, milliseconds, and picoseconds a round, 0
# where a count is 0 and there is no round.
use "lib/std/io.aw"
use "lib/async/task.aw"
use "lib/async/time.aw"
use "bench/async/num.aw"
async fn work n k s
	while n
		await asyncyield
		x=s+n
		i=0
		while i<k
			x=x*5+i
			y=x>>7
			x^=y
			i+=1
		end
		s+=x
		n-=1
	end
	ret s
end
call argv 1 as p n
call decnum p n as tasks
call argv 2 as p n
call decnum p n as waits
call argv 3 as p n
call decnum p n as steps
alloc tasks*8 as hs
call asyncclock as t0
i=0
while i<tasks
	call work waits steps i as h
	store hs+i*8 h
	i+=1
end
r=0
i=0
while i<tasks
	load hs+i*8 as h
	await h as x
	r+=x
	i+=1
end
call asyncclock as t1
n=tasks*waits*steps
call putn r
call print " "
call putn n
call print " "
call putn (t1-t0)/1000000
call print " "
# no round, no time a round
p=0
if n
	p=(t1-t0)*1000/n
end
call putn p
call print "\n"
