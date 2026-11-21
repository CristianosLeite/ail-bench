# bench/async/num.aw -- for the programs of this directory, which take
# numbers as arguments
# the decimal number in the n bytes at p
fn decnum p n
	v=0
	while n
		loadb p as c
		v=v*10+c-48
		p+=1
		n-=1
	end
	ret v
end
