# the clock of the algorithm harness: after algoclk it never goes back,
# is the monotonic clock of the system call to within a second, and can
# be read with the stack 8 bytes off a multiple of 16 and on one, which
# a function with a local of 8 bytes and one without make between them
use "lib/std/io.aw"
use "bench/algo/harness.aw"
buf ts 16
fn raw
	sys clock_gettime 1 ts
	load ts as s
	load ts+8 as n
	ret s*1000000000+n
end
fn even
	call algonow as a
	call algonow as b
	ret b-a
end
fn odd
	local x 8
	store x 0
	call algonow as a
	call algonow as b
	ret b-a
end
call algoclk
call raw as r
call algonow as a
call even as d
call odd as e
call algonow as b
call putn (a-r<1000000000)+(b>=a)+(d<1000000000)+(e<1000000000)
call print "\n"
