# bench/async/mem.aw -- the memory of a task that waits:
#   mem <tasks>
# Makes that many tasks, each asleep in a timer with three variables
# alive, and prints the bytes of resident memory that one of them added.
use "lib/std/io.aw"
use "lib/async/task.aw"
use "lib/async/time.aw"
use "bench/async/num.aw"
buf sb 256
data statm "/proc/self/statm" 0
# resident memory in bytes: the second number of the file, in pages
fn resident
	sys open statm 0 as fd
	sys read fd sb 255 as n
	sys close fd
	i=0
	while i<n
		loadb sb+i as c
		i+=1
		if c==32
			j=i
			while j<n
				loadb sb+j as c
				if c==32
					call decnum sb+i j-i as v
					ret v*4096
				end
				j+=1
			end
		end
	end
	ret 0
end
async fn sleeper a b c
	await asyncsleep 600000
	ret a+b+c
end
call argv 1 as p n
call decnum p n as tasks
call resident as r0
i=0
while i<tasks
	call sleeper i i+1 i+2 as h
	call asyncdetach h
	i+=1
end
# poll every task once, up to its await
label more
load asyncq as t
if t
	call asyncstep
	jmp more
end
call resident as r1
call putn (r1-r0)/tasks
call print "\n"
call asyncstop
