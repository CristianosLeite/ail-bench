# Fill n 64-bit words (n is the 8 bytes on stdin, at most 16777216) and
# add them up sixteen times: the time is memory, first the kernel's pages,
# then reading them.
use "lib/std/io.aw"
buf in 8
buf m 134217728
fn pass lo hi k
	s=0
	if k
		while lo<hi
			load m+lo*8 as x
			s+=x^(x>>7)
			lo+=1
		end
	else
		while lo<hi
			store m+lo*8 lo*2654435761
			lo+=1
		end
	end
	ret s
end
sys read 0 in 8
load in as n
s=0
k=0
while k<17
	call pass 0 n k as x
	s+=x
	k+=1
end
call putn s
