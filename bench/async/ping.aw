# bench/async/ping.aw -- load for an echo server:
#   ping <connections> <rounds> [port]
# Each connection is a task that sends 64 bytes and reads them back,
# <rounds> times. Without a port the server runs in this process, as
# more tasks of the same thread; with one it is another process on this
# machine (echod). One line: connections, rounds, round trips in all,
# milliseconds, round trips a second, and the time of a round trip in
# microseconds: mean, median and the one that 99 of 100 are within.
use "lib/std/io.aw"
use "lib/async/task.aw"
use "lib/async/time.aw"
use "lib/async/io.aw"
use "bench/async/echo.aw"
use "bench/async/num.aw"
# round trips by their time in microseconds; the last cell is the rest
const cells 1000000
buf hist 8000008
var total failed
async fn client port rounds
	local out 64
	local in 64
	await asyncconnect 0x7f000001 port as c
	if c>-4096
		failed+=1
		ret 0
	end
	while rounds
		call asyncclock as t0
		await asyncwriteall c out 64 as n
		got=0
		while got<64
			await asyncread c in+got 64-got as n
			if n-1>=64
				failed+=1
				call asyncclose c
				ret 0
			end
			got+=n
		end
		call asyncclock as t1
		us=(t1-t0)/1000
		total+=us
		if us>cells
			us=cells
		end
		load hist+us*8 as k
		store hist+us*8 k+1
		rounds-=1
	end
	call asyncclose c
end
# the time that the given share of a hundred round trips is within
fn within share n
	want=n*share/100
	seen=0
	i=0
	while i<=cells
		load hist+i*8 as k
		seen+=k
		if seen>=want
			ret i
		end
		i+=1
	end
	ret cells
end
call argv 1 as p n
call decnum p n as conns
call argv 2 as p n
call decnum p n as rounds
call argv 3 as p n
if n
	call decnum p n as port
else
	call asynclisten 0x7f000001 0 as l
	call asyncport l as port
	call echoserve l as sv
	call asyncdetach sv
end
alloc conns*8 as hs
call asyncclock as t0
i=0
while i<conns
	call client port rounds as h
	store hs+i*8 h
	i+=1
end
i=0
while i<conns
	load hs+i*8 as h
	await h
	i+=1
end
call asyncclock as t1
n=conns*rounds
ms=(t1-t0)/1000000
call putn conns
call print " "
call putn rounds
call print " "
call putn n
call print " "
call putn ms
call print " "
call putn n*1000000000/(t1-t0+1)
call print " "
call putn total/n
call print " "
call within 50 n as v
call putn v
call print " "
call within 99 n as v
call putn v
call print "\n"
call asyncstop
sys exit_group failed!=0
