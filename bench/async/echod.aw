# bench/async/echod.aw -- a TCP echo server on one thread, a task for
# each connection:  echod [port]  (default 8080; with 0 the kernel
# chooses the port). It prints the port it listens on and runs until it
# is killed.
use "lib/std/io.aw"
use "lib/async/task.aw"
use "lib/async/time.aw"
use "lib/async/io.aw"
use "bench/async/echo.aw"
use "bench/async/num.aw"
port=8080
call argv 1 as p n
if n
	call decnum p n as port
end
call asynclisten 0 port as l
if l>-4096
	call die "echod: cannot listen\n"
end
call asyncport l as port
call putn port
call print "\n"
await echoserve l
