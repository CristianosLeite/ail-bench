# memsum_seq.aw as tasks, each pass cut into pieces; stdin as for
# collatz_par.aw.
use "lib/std/io.aw"
use "lib/std/task.aw"
buf in 24
buf hs 8388608
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
sys read 0 in 24
load in as n
load in+8 as w
load in+16 as t
call workers w-1
c=(n+t-1)/t
s=0
k=0
while k<17
	i=0
	lo=0
	while lo<n
		hi=lo+c
		if hi>n
			hi=n
		end
		spawn pass lo hi k as h
		store hs+i*8 h
		i+=1
		lo=hi
	end
	while i
		i-=1
		load hs+i*8 as h
		join h as x
		s+=x
	end
	k+=1
end
call putn s
