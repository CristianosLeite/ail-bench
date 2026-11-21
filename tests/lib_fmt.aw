# doubles with two and three digits after the point as "%.2f" and "%.3f"
# print them: the nearest decimal to the exact value, which for 12.345
# and 2.675 lies below the tie and for 0.125 and 2.5 is a tie that goes
# to the even digit
use "lib/std/io.aw"
use "lib/std/bytes.aw"
use "tools/lib/fmt.aw"
buf b 24
fn show n d k
	itof n as x
	itof d as y
	fdiv x y as x
	call fmtfix b x k
	call byaddb b 32
end
call show 12345 1000 2
call show 2675 1000 2
call show 1 8 2
call show 5 2 0
call show 7 2 0
call show 1 3 3
call show 2 3 3
call show 0 1 2
call show 1000001 1000 2
call show 999999 1000000 2
call show 123456789 1000000 2
call show 11 10 3
call show 1 1000 2
call show 5 1000 2
call show 6 1000 2
itof 3 as x
itof 0 as y
fsub y x as x
call fmtfix b x 2
call byaddb b 32
fdiv x y as x
call fmtfix b x 2
call byaddb b 10
load b as p
load b+8 as n
call print p n
