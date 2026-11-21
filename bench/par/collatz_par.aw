# collatz_seq.aw as tasks. Stdin is three 8-byte numbers: n, the number
# of threads that run tasks (the pool has one less, the main thread runs
# tasks in its joins) and the number of tasks the range is cut into.
use "lib/std/io.aw"
use "lib/std/task.aw"
buf in 24
buf hs 8388608
fn steps lo hi
	s=0
	while lo<hi
		n=lo
		while n!=1
			if n&1
				n=n*3+1
			else
				n>>=1
			end
			s+=1
		end
		lo+=1
	end
	ret s
end
sys read 0 in 24
load in as n
load in+8 as w
load in+16 as t
call workers w-1
c=(n+t-1)/t
i=0
lo=1
while lo<n
	hi=lo+c
	if hi>n
		hi=n
	end
	spawn steps lo hi as h
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
