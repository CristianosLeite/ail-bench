# The sum of the Collatz step counts of the numbers 1 to n-1, n being the
# 8 bytes on stdin: even work, no memory. Printed in decimal.
use "lib/std/io.aw"
buf in 8
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
sys read 0 in 8
load in as n
call steps 1 n as s
call putn s
