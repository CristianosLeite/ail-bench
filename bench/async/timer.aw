# bench/async/timer.aw -- two tasks that each print a line at their own
# pace: three every 100 milliseconds and two every 150. One thread runs
# both and sleeps in between.
use "lib/std/io.aw"
use "lib/async/task.aw"
use "lib/async/time.aw"
async fn tick n ms
	while n
		await asyncsleep ms
		call putn ms
		call print "\n"
		n-=1
	end
end
call tick 3 100 as a
call tick 2 150 as b
await a
await b
