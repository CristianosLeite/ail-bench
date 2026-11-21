# The number of primes below n, the 8 bytes on stdin, by trial division:
# the work grows along the range, so equal pieces are unequal work.
use "lib/std/io.aw"
buf in 8
fn count lo hi
	c=0
	while lo<hi
		p=lo>1
		d=2
		while d*d<=lo
			ifz lo%d
				p=0
				d=lo
			end
			d+=1
		end
		c+=p
		lo+=1
	end
	ret c
end
sys read 0 in 8
load in as n
call count 0 n as s
call putn s
