# bench/algo/harness.aw -- what the algorithm programs of bench/ share:
# the input, the arrays, the clock and the output. bench/algo/harness.h
# and harness.rs are the same in C and in Rust.
#
# A program reads five words of 8 bytes: a seed, a count n, a shape (0
# random, 1 rising, 2 falling, 3 few distinct values), how often to run,
# and a mode. Anything shorter, or a count of 2^28 or more, ends it with
# the status 1. It makes its input from the seed with splitmix64, runs its
# kernels that often, each time on the input as it was made, and takes
# the time of the monotonic clock around each kernel and around nothing
# else. In mode 0 it writes a checksum of the last result, 8 bytes, and
# then the nanoseconds of each kernel, summed over the runs, 8 bytes
# each; in mode 1 it writes the words of its input and in mode 2 those of
# its result.
#
# The clock is the function __vdso_clock_gettime that the kernel maps
# into the process, which is what clock_gettime of the C library and
# Instant::now of Rust call: about 6 ns a reading where the system call
# takes 45, so that a kernel run often costs the same clock in the three
# languages. algoclk finds it once; where it is not found the clock is
# the system call. The function is called with the stack as it is, 8
# bytes off a multiple of 16 or not, which the vDSO of Linux does not
# mind; it keeps the registers that hold variables.
#
# A program names its kernels with ref and calls one of algosort,
# algosearch, algohash, algograph, algonum and algocoll, which do not
# return an
# error: they end the program. Every name of this file starts with
# "algo".
buf algoin 40
buf algoks 24
buf algoout 32
buf algots 16
buf algorng 8
buf algost 64

# the function that reads the clock, the path of the values the kernel
# gave the process, and the name the vDSO has for its function
buf algovd 8
buf algoaux 1024
data algoauxp "/proc/self/auxv" 0
data algovdn "__vdso_clock_gettime" 0

# the clock by a system call, called as the function of the vDSO is
fn algosysc algoz algoc algop
	sys clock_gettime algoc algop
end

# find the clock of the vDSO: the kernel maps that file into every
# process and gives its address as the value 33 (AT_SYSINFO_EHDR) of the
# auxiliary vector. It is an ELF file: the program headers name the
# dynamic table, that the symbols, their names and (in the hash table)
# their count. Without any of those the clock stays the system call.
fn algoclk
	ref algosysc as algof
	store algovd algof
	sys open algoauxp 0 0 as algof
	ifslt algof 0
		ret 0
	end
	algon=0
	label more
	sys read algof algoaux+algon 1024-algon as algor
	ifsgt algor 0
		algon+=algor
		jlt algon 1024 more
	end
	sys close algof
	algob=0
	algoi=0
	while algoi+16<=algon
		load algoaux+algoi as algot
		if algot==33
			load algoaux+algoi+8 as algob
		end
		algoi+=16
	end
	ifz algob
		ret 0
	end
	# the distance of the file from its addresses, and the dynamic table
	load algob+32 as algop
	algop+=algob
	load2 algob+54 as algoe
	load2 algob+56 as algok
	algod=0
	algox=0
	algoh=0
	while algok
		load4 algop as algot
		if (algot==1)&(algoh==0)
			load algop+8 as algox
			load algop+16 as algov
			algox=algob+algox-algov
			algoh=1
		end
		if algot==2
			load algop+16 as algod
		end
		algop+=algoe
		algok-=1
	end
	ifz (algod!=0)&algoh
		ret 0
	end
	algod+=algox
	algoh=0
	algos=0
	algoy=0
	load algod as algot
	while algot
		load algod+8 as algov
		if algot==4
			algoh=algov+algox
		end
		if algot==5
			algos=algov+algox
		end
		if algot==6
			algoy=algov+algox
		end
		algod+=16
		load algod as algot
	end
	ifz (algoh!=0)&(algos!=0)&(algoy!=0)
		ret 0
	end
	load4 algoh+4 as algok
	while algok
		load4 algoy as algop
		load2 algoy+6 as algot
		algop+=algos
		algoi=0
		loadb algop as algoa
		loadb algovdn as algoc
		while (algoa==algoc)&(algoa!=0)
			algoi+=1
			loadb algop+algoi as algoa
			loadb algovdn+algoi as algoc
		end
		if (algoa==algoc)&(algot!=0)
			load algoy+8 as algov
			store algovd algov+algox
			ret 1
		end
		algoy+=24
		algok-=1
	end
	ret 0
end

# the monotonic clock in nanoseconds
fn algonow
	load algovd as algof
	callp algof 0 1 algots as algos
	load algots as algos
	load algots+8 as algon
	ret algos*1000000000+algon
end

# the next number of splitmix64
fn algornd
	load algorng as algoz
	algoz+=0x9e3779b97f4a7c15
	store algorng algoz
	algoz=(algoz^algoz>>30)*0xbf58476d1ce4e5b9
	algoz=(algoz^algoz>>27)*0x94d049bb133111eb
	ret algoz^algoz>>31
end

# read the five words; the count, the shape, the runs and the mode
fn algoread
	call algoclk
	algon=0
	while algon<40
		sys read 0 algoin+algon 40-algon as algor
		ifslt algor 1
			sys exit 1
		end
		algon+=algor
	end
	load algoin as algos
	store algorng algos
	load algoin+8 as algon
	if algon>>28
		sys exit 1
	end
	load algoin+16 as algos
	load algoin+24 as algor
	load algoin+32 as algom
	ret algon algos algor algom
end

# algon words of zeros
fn algomem algon
	alloc algon*8 as algop
	ifz algop
		sys exit 1
	end
	ret algop
end

# write algon bytes at algop
fn algowrite algop algon
	while algon
		sys write 1 algop algon as algor
		ifslt algor 1
			sys exit 1
		end
		algop+=algor
		algon-=algor
	end
end

# copy algon words from algos to algod
fn algocopy algod algos algon
	algoi=0
	while algoi<algon
		load algos+algoi*8 as algov
		store algod+algoi*8 algov
		algoi+=1
	end
end

# the checksum of algon words at algoa
fn algosum algoa algon
	algoh=0
	algoi=0
	while algoi<algon
		load algoa+algoi*8 as algov
		algoh=algoh*31+algov
		algoi+=1
	end
	ret algoh
end

# write what the mode algom asks for: the algon words of the input at
# algoa, the algol words of the result at algor, or the checksum of the
# result and the algok times that are in algoout
fn algoend algom algoa algon algor algol algok
	if algom==1
		call algowrite algoa algon*8
		ret 0
	end
	if algom==2
		call algowrite algor algol*8
		ret 0
	end
	call algosum algor algol as algoh
	store algoout algoh
	call algowrite algoout algok*8+8
end

# fill algon words at algoa by the shape algos
fn algofill algoa algon algos
	algox=0
	algoi=0
	while algoi<algon
		call algornd as algov
		if algos==1
			algox+=algov&0xffff
			algov=algox
		end
		if algos==2
			algox-=algov&0xffff
			algov=algox
		end
		if algos==3
			algov&=15
		end
		store algoa+algoi*8 algov
		algoi+=1
	end
end

# a sort: the kernel algok gets the array, the count and a scratch array
# of the same size. The input is the array, the result the array sorted.
fn algosort algok
	call algoread as algon algos algor algom
	call algomem algon*3 as algoa
	algow=algoa+algon*8
	algot=algow+algon*8
	call algofill algoa algon algos
	call algocopy algow algoa algon
	algons=0
	while algor
		call algocopy algow algoa algon
		call algonow as algob
		callp algok algow algon algot as algox
		call algonow as algoe
		algons+=algoe-algob
		algor-=1
	end
	store algoout+8 algons
	tail algoend algom algoa algon algow algon 1
end

# a search: the kernel algok gets a sorted array, the count, as many keys
# and the place for the result of each. The input is the array and then
# the keys; the steps of the array are below 65536, for the shape 3
# below 2.
fn algosearch algok
	call algoread as algon algos algor algom
	call algomem algon*3 as algoa
	algoq=algoa+algon*8
	algow=algoq+algon*8
	algomask=0xffff
	if algos==3
		algomask=1
	end
	algox=0
	algoi=0
	while algoi<algon
		call algornd as algov
		algox+=algov&algomask
		store algoa+algoi*8 algox
		algoi+=1
	end
	algoi=0
	while algoi<algon
		call algornd as algov
		store algoq+algoi*8 algov%(algox+2)
		algoi+=1
	end
	algons=0
	while algor
		call algonow as algob
		callp algok algoa algon algoq algow as algoy
		call algonow as algoe
		algons+=algoe-algob
		algor-=1
	end
	store algoout+8 algons
	tail algoend algom algoa algon*2 algow algon 1
end

# a hash table: algoki puts algon keys into a table of 16 bytes a place
# (key and value, the key 0 an empty place) given with its mask, algokh
# and algokm look keys up and write the value of each, or -1. The table
# has at least twice the places of the keys. The input is the keys, odd
# numbers (for the shape 3 below 256), and then as many even numbers that
# are in no table; the result is what the two lookups wrote.
fn algohash algoki algokh algokm
	call algoread as algon algos algor algom
	algoc=16
	while algoc<algon*2
		algoc*=2
	end
	call algomem algon*4+algoc*2 as algoa
	algoq=algoa+algon*8
	algow=algoq+algon*8
	algot=algow+algon*16
	algoi=0
	while algoi<algon
		call algornd as algov
		if algos==3
			algov&=255
		end
		store algoa+algoi*8 algov|1
		algoi+=1
	end
	algoi=0
	while algoi<algon
		call algornd as algov
		store algoq+algoi*8 (algov|1)<<1
		algoi+=1
	end
	algox=0
	algoy=0
	algoz=0
	while algor
		algoi=0
		while algoi<algoc*2
			store algot+algoi*8 0
			algoi+=1
		end
		call algonow as algob
		callp algoki algot algoc-1 algoa algon as algov
		call algonow as algoe
		algox+=algoe-algob
		call algonow as algob
		callp algokh algot algoc-1 algoa algon algow as algov
		call algonow as algoe
		algoy+=algoe-algob
		call algonow as algob
		callp algokm algot algoc-1 algoq algon algow+algon*8 as algov
		call algonow as algoe
		algoz+=algoe-algob
		algor-=1
	end
	store algoout+8 algox
	store algoout+16 algoy
	store algoout+24 algoz
	tail algoend algom algoa algon*2 algow algon*2 3
end

# a graph of algon vertices: the neighbours of the vertex v are the words
# of the second array from the index in word v of the first array up to
# the index in word v+1. A vertex has up to 15 neighbours, for the shape
# 3 at most one. algokb and algokd get the two arrays, the count, a byte
# a vertex that is zero, an array to work in with a word for each edge
# and one more, and the place for the vertices in the order they are
# reached; algokb does not get the work array. The input is the two
# arrays, the result the two orders.
fn algograph algokb algokd
	call algoread as algon algos algor algom
	algomask=15
	if algos==3
		algomask=1
	end
	# the first array, the neighbours, two orders, the work array, the bytes
	call algomem algon*35+4 as algoa
	algoq=algoa+algon*8+8
	algox=0
	algoi=0
	while algoi<algon
		store algoa+algoi*8 algox
		call algornd as algov
		algov&=algomask
		while algov
			call algornd as algoy
			store algoq+algox*8 algoy%algon
			algox+=1
			algov-=1
		end
		algoi+=1
	end
	store algoa+algon*8 algox
	algow=algoq+algox*8
	algot=algow+algon*16
	algoz=algot+algox*8+8
	algoy=0
	algoh=0
	while algor
		algoi=0
		while algoi<algon
			storeb algoz+algoi 0
			algoi+=1
		end
		call algonow as algob
		callp algokb algoa algoq algon algoz algow as algov
		call algonow as algoe
		algoy+=algoe-algob
		algoi=0
		while algoi<algon
			storeb algoz+algoi 0
			algoi+=1
		end
		call algonow as algob
		callp algokd algoa algoq algon algoz algot algow+algon*8 as algov
		call algonow as algoe
		algoh+=algoe-algob
		algor-=1
	end
	store algoout+8 algoy
	store algoout+16 algoh
	tail algoend algom algoa algon+1+algox algow algon*2 2
end

# kernels on numbers: up to three, 0 for none after the last, each given
# two arrays of doubles, the place for as many results, all zero at
# first, and the count. With algosq an array has the count times the
# count of numbers, a square, and a count whose square is 2^28 or more
# ends the program with the status 1. A number is the lowest 20 bits of
# the word the shape gives, divided by 1024, less 512: every language
# gets the same bits. The input is the two arrays, the result the
# results of the kernels one behind the other.
fn algonum algoka algokb algokc algosq
	call algoread as algon algos algor algom
	algol=algon
	if algosq
		algol=algon*algon
		if algol>>28
			sys exit 1
		end
	end
	store algoks algoka
	store algoks+8 algokb
	store algoks+16 algokc
	algok=(algoka!=0)+(algokb!=0)+(algokc!=0)
	call algomem algol*(2+algok) as algoa
	algow=algoa+algol*16
	call algofill algoa algol*2 algos
	itof 1024 as algod
	itof 512 as algoh
	algoi=0
	while algoi<algol*2
		load algoa+algoi*8 as algov
		itof algov&0xfffff as algov
		fdiv algov algod as algov
		fsub algov algoh as algov
		store algoa+algoi*8 algov
		algoi+=1
	end
	while algor
		algoi=0
		while algoi<algok
			load algoks+algoi*8 as algop
			call algonow as algob
			callp algop algoa algoa+algol*8 algow+algoi*algol*8 algon as algox
			call algonow as algoe
			load algoout+8+algoi*8 as algox
			store algoout+8+algoi*8 algox+algoe-algob
			algoi+=1
		end
		algor-=1
	end
	tail algoend algom algoa algol*2 algow algol*algok algok
end

# collections: up to three kernels, run one after the other, each given
# the algon keys, their number, a place for as many words of result, and
# 64 bytes that are zero before the first of a run and in which one
# kernel may leave a collection for the next; the last gives it back.
# The input is the keys, by the shape; the result is what the kernels
# wrote, one behind the other.
fn algocoll algoka algokb algokc
	call algoread as algon algos algor algom
	store algoks algoka
	store algoks+8 algokb
	store algoks+16 algokc
	algok=(algoka!=0)+(algokb!=0)+(algokc!=0)
	call algomem algon*(1+algok) as algoa
	algow=algoa+algon*8
	call algofill algoa algon algos
	while algor
		algoi=0
		while algoi<8
			store algost+algoi*8 0
			algoi+=1
		end
		algoi=0
		while algoi<algok
			load algoks+algoi*8 as algop
			call algonow as algob
			callp algop algoa algon algow+algoi*algon*8 algost as algox
			call algonow as algoe
			load algoout+8+algoi*8 as algox
			store algoout+8+algoi*8 algox+algoe-algob
			algoi+=1
		end
		algor-=1
	end
	tail algoend algom algoa algon algow algon*algok algok
end
