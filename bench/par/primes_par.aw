# primes_seq.aw as tasks; stdin as for collatz_par.aw.
use "lib/std/io.aw"
use "lib/std/task.aw"
buf in 24
buf hs 8388608
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
sys read 0 in 24
load in as n
load in+8 as w
load in+16 as t
call workers w-1
c=(n+t-1)/t
i=0
lo=0
while lo<n
	hi=lo+c
	if hi>n
		hi=n
	end
	spawn count lo hi as h
	store hs+i*8 h
	i+=1
	lo=hi
end
s=0
while i
	i-=1
	load hs+i*8 as h
	join h as x
	s+=x
end
call putn s
