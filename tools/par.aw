# tools/par.aw -- check and time the task programs of bench/par against
# their sequential forms.
#
#   build/tools/par [--check] [--grid] [--runs N] [name ...]
#                                           (default: every program)
#
# Run from the top of the repository after make.
#
# A program is bench/par/<name>_seq.aw and bench/par/<name>_par.aw, the
# same work written without and with spawn and join; both are compiled
# by build/ailc into build/par/. The sequential one reads its size from
# stdin, the other the size, the number of threads that run tasks and
# the number of tasks. Every run must print what the sequential program
# prints.
#
# --check runs small sizes at several thread and task counts and times
# nothing; make test does that. Without it the sizes below are timed:
# the median of the runs, on all processors, each run a new process
# (lib/std/proc.aw), timed from before its start to after its end. Three
# tables are printed: elapsed time by threads, elapsed time by the number
# of tasks the same work is cut into, and the tokens of both sources,
# which build/tools/tokens counts if it is built and the files it reads
# are there (make tools, tools/pin.sh; see tools/tokens.sh).
#
# --grid times every number of threads of the first table with every
# number of tasks of the second but the last, and prints for each program
# a table of the sequential time divided by the time, which shows the
# sizes of task that are worth having at each number of threads.
#
# A run gets 120 seconds. Status 0; 1 after a failed check, or with a
# line "par: ..." on stderr.
use "lib/std/io.aw"
use "lib/std/bytes.aw"
use "lib/std/file.aw"
use "lib/std/proc.aw"
use "tools/lib/fmt.aw"

# the programs: the name, the size timed, the size checked
data progs 7 "collatz" 6 "primes" 6 "memsum"
const nprogs 3
# the threads of the first table and its number of tasks; the tasks of
# the second table and its threads; the threads and tasks of a check
const tasks 160
const nthreads 6
const nsweep 6
const nchecks 6
data sailc "build/ailc" 0
data stokens "build/tools/tokens" 0
data sdashc "-c" 0
data shash "#" 0

# the numbers above that do not fit a byte, filled in at the start
buf sizes 48
buf threads 48
buf sweep 48
buf sweept 16
buf checks 96
# a line for stdout; the output wanted and the output of a run; the
# numbers a run reads, and how many; paths, each with a NUL byte; the
# arguments of a child; the times of the runs of one figure
buf ob 24
buf want 24
buf got 24
buf nums 24
buf strs 24
buf av 80
buf times 24
# the results as doubles: for each program the sequential time and one
# for each number of threads, then for each of the two sweeps one for
# each number of tasks
buf rows 168
buf sweeps 288
# the programs asked for and their number
buf sel 24
var gnsel gnnums

fn fail m mn
	call eprint "par: "
	call eprint m mn
	call eprint "\n"
	sys exit_group 1
end

# 0 is no memory
fn must r
	ifz r
		call fail "out of memory"
	end
end

fn add b p n
	call byadd b p n as r
	call must r
end

fn addn b v
	call byaddn b v as r
	call must r
end

fn item p i
	while i
		loadb p as n
		p+=n+1
		i-=1
	end
	loadb p as n
	ret p+1 n
end

fn say
	load ob as p
	load ob+8 as n
	call print p n
	call bycut ob 0
end

# the unsigned decimal number of n bytes at p, and 1; or 0 and 0
fn num p n
	ifz n
		ret 0 0
	end
	v=0
	while n
		loadb p as c
		if c-48>=10
			ret 0 0
		end
		v=v*10+c-48
		p+=1
		n-=1
	end
	ret v 1
end

# 1 if the n bytes at p are the m bytes at q
fn same p n q m
	if n!=m
		ret 0
	end
	while n
		loadb p as c
		loadb q as d
		if c!=d
			ret 0
		end
		p+=1
		q+=1
		n-=1
	end
	ret 1
end

# a path of three pieces into strs, with a NUL byte: where it starts
fn path a an b bn c cn
	load strs+8 as o
	call add strs a an
	call add strs b bn
	call add strs c cn
	call byaddb strs 0 as r
	call must r
	ret o
end

# the address of the path that starts at o in strs
fn at o
	load strs as p
	ret p+o
end

# the text of a path at p, with the numbers of the run, onto stderr after
# "par: ": the start of every message about a run
fn blame p
	call strlen p as n
	call eprint "par: "
	call eprint p n
	call bycut ob 0
	call add ob " ("
	i=0
	while i<gnnums
		if i
			call add ob ", "
		end
		load nums+i*8 as v
		call addn ob v
		i+=1
	end
	if gnnums==1
		call add ob ","
	end
	call add ob "): "
	load ob as q
	load ob+8 as n
	call eprint q n
	call bycut ob 0
end

# compile bench/par/<name>_<kind>.aw into build/par/<name>_<kind>, whose
# path is returned as a place in strs
fn build np nn kind kn
	load strs+8 as mark
	call path "bench/par/" np nn "_" as s
	load strs+8 as n
	store strs+8 n-1
	call path kind kn ".aw" "" as o
	call path "build/par/" np nn "_" as e
	load strs+8 as n
	store strs+8 n-1
	call path kind kn "" "" as o
	call at s as sp
	call at e as ep
	call strlen sp as sn
	call strlen ep as en
	call fileopen sp sn 0 0 as i
	call fileopen ep en 0x241 493 as o
	ifslt i|o 0
		call eprint "par: cannot compile "
		call eprint sp sn
		call eprint "\n"
		sys exit_group 1
	end
	store av sailc
	store av+8 0
	call procstart av i o -1 as pid
	sys close i
	sys close o
	call procwait pid 0 as st
	if st
		call eprint "par: cannot compile "
		call eprint sp sn
		call eprint "\n"
		sys exit_group 1
	end
	ret e
end

# run the program at the path e with the numbers in nums on stdin: its
# time in nanoseconds, and its output in got
fn run e
	load nums as p
	call filewrite "build/par/input" p gnnums*8 as r
	call fileopen "build/par/input" 0 0 as i
	call fileopen "build/par/output" 0x241 420 as o
	ifslt r|i|o 0
		call fail "cannot use build/par"
	end
	call at e as ep
	store av ep
	store av+8 0
	call procnow as t
	call procstart av i o -1 as pid
	ifslt pid 0
		call fail "cannot start a process"
	end
	call procwait pid 120000 as st
	call procnow as u
	sys close i
	sys close o
	if st
		call blame ep
		if st==-1000
			call eprint "ran longer than 120 seconds\n"
		else
			call eprint "exit status "
			ifslt st 0
				call eprint "-"
				st=0-st
			end
			call addn ob st
			call add ob "\n"
			load ob as q
			load ob+8 as n
			call eprint q n
		end
		sys exit_group 1
	end
	call bycut got 0
	call fileread "build/par/output" got as r
	ifslt r 0
		call fail "cannot use build/par"
	end
	ret u-t
end

# set the numbers of a run: k of a, b and c
fn args k a b c
	call bycut nums 0
	call byneed nums 24 as r
	call must r
	load nums as p
	store p a
	store p+8 b
	store p+16 c
	store nums+8 24
	gnnums=k
end

# 1 if the output of the last run is the output wanted
fn right
	load got as p
	load got+8 as n
	load want as q
	load want+8 as m
	call same p n q m as r
	ret r
end

# the output of the last run is the output wanted from now on
fn keep
	call bycut want 0
	load got as p
	load got+8 as n
	call add want p n
end

# the median of the times of that many runs of the program at e with the
# numbers set, in milliseconds as a double; every run must print what is
# wanted
fn time e runs
	call bycut times 0
	call byneed times runs*8 as r
	call must r
	load times as tp
	i=0
	while i<runs
		call run e as t
		call right as r
		ifz r
			call at e as ep
			call blame ep
			call eprint "wrong output\n"
			sys exit_group 1
		end
		# into its place among the times before it
		j=i
		while j
			load tp+j*8-8 as w
			jle w t put
			store tp+j*8 w
			j-=1
		end
		label put
		store tp+j*8 t
		i+=1
	end
	load tp+runs/2*8 as v
	if runs&1
		itof v as m
	else
		load tp+runs/2*8-8 as w
		itof v+w as m
		itof 2 as w
		fdiv m w as m
	end
	itof 1000000 as w
	fdiv m w as m
	ret m
end

# the double x into ob with d digits after the point
fn cell x d
	call fmtfix ob x d as r
	call must r
end

# every program asked for, at its small size: the output of each pair of
# threads and tasks must be the sequential one. 1 if any was not
fn check
	bad=0
	k=0
	while k<gnsel
		load sel+k*8 as pi
		call item progs pi as np nn
		call bycut strs 0
		call build np nn "seq" as seq
		call build np nn "par" as par
		load sizes+pi*16+8 as n
		call args 1 n 0 0
		call run seq as t
		call keep
		# a bit for each pair that fails
		f=0
		i=0
		while i<nchecks
			load checks+i*16 as th
			load checks+i*16+8 as ta
			call args 3 n th ta
			call run par as t
			call right as r
			ifz r
				f|=1<<i
			end
			i+=1
		end
		if f
			call add ob "FAIL par "
			bad=1
		else
			call add ob "ok   par "
		end
		call add ob np nn
		call add ob "\n"
		i=0
		while i<nchecks
			if f>>i&1
				load checks+i*16 as th
				load checks+i*16+8 as ta
				call add ob "     "
				call addn ob th
				call add ob " threads, "
				call addn ob ta
				call add ob " tasks: not the sequential output\n"
			end
			i+=1
		end
		call say
		k+=1
	end
	ret bad
end

# the number of lines of the file at the path p n that have more than
# white space before a #
fn codelines p n
	call bycut got 0
	call fileread p n got as r
	ifslt r 0
		call fail "cannot read a source of bench/par"
	end
	load got as q
	e=q+r
	c=0
	any=0
	skip=0
	while q<e
		loadb q as b
		if b==10
			c+=any
			any=0
			skip=0
		else
			if b==35
				skip=1
			end
			ifz skip
				if ((b-9<5)|(b-28<5))==0
					any=1
				end
			end
		end
		q+=1
	end
	ret c+any
end

# the table of the tokens of both sources of every program asked for
fn tokens
	call bycut strs 0
	k=0
	while k<gnsel
		load sel+k*8 as pi
		call item progs pi as np nn
		call path "bench/par/" np nn "_seq.aw" as o
		store av+24+k*16 o
		call path "bench/par/" np nn "_par.aw" as o
		store av+32+k*16 o
		k+=1
	end
	store av stokens
	store av+8 sdashc
	store av+16 shash
	k=0
	while k<gnsel*2
		load av+24+k*8 as o
		call at o as p
		store av+24+k*8 p
		k+=1
	end
	store av+24+k*8 0
	call fileopen "build/par/tokens" 0x241 420 as o
	call fileopen "/dev/null" 1 0 as e
	ifslt o|e 0
		call fail "cannot use build/par"
	end
	call procstart av -1 o e as pid
	sys close o
	sys close e
	call procwait pid 120000 as st
	if st
		call add ob "\n(no token table: build/tools/tokens is not built or lacks its pinned files)\n"
		call say
		ret 0
	end
	call bycut want 0
	call fileread "build/par/tokens" want as r
	call add ob "\n| program | sequential | tasks | lines added |\n|---|---:|---:|---:|\n"
	load want as q
	k=0
	while k<gnsel
		load sel+k*8 as pi
		call item progs pi as np nn
		call add ob "| "
		call add ob np nn
		i=0
		while i<2
			# a line of the counts: the number, a space, the path
			call add ob " | "
			load want as b
			load want+8 as n
			while (q<b+n)
				loadb q as c
				jge c-48 10 counted
				call byaddb ob c as r
				call must r
				q+=1
			end
			label counted
			while q<b+n
				loadb q as c
				q+=1
				jeq c 10 nextline
			end
			label nextline
			i+=1
		end
		load av+24+k*16 as p
		call strlen p as n
		call codelines p n as a
		load av+32+k*16 as p
		call strlen p as n
		call codelines p n as b
		call add ob " | "
		call addn ob b-a
		call add ob " |\n"
		k+=1
	end
	call say
end

# for every program asked for, the sequential time divided by the time
# at each number of tasks, a row, and of threads, a column
fn grid runs
	k=0
	while k<gnsel
		load sel+k*8 as pi
		call item progs pi as np nn
		call bycut strs 0
		call build np nn "seq" as seq
		call build np nn "par" as par
		load sizes+pi*16 as n
		call args 1 n 0 0
		call run seq as t
		call keep
		call time seq runs as base
		call add ob "\n"
		call add ob np nn
		call add ob ", sequential "
		call cell base 1
		call add ob " ms: that time divided by the time with tasks, median of "
		call addn ob runs
		call add ob " runs\n\n| tasks | 1 | 2 | 4 | 8 | 14 | 20 |\n|---:|---:|---:|---:|---:|---:|---:|\n"
		call say
		j=0
		while j<nsweep-1
			load sweep+j*8 as ta
			call add ob "| "
			call addn ob ta
			call add ob " |"
			i=0
			while i<nthreads
				load threads+i*8 as th
				call args 3 n th ta
				call time par runs as m
				fdiv base m as m
				call add ob " "
				call cell m 2
				call add ob " |"
				i+=1
			end
			call add ob "\n"
			call say
			j+=1
		end
		k+=1
	end
	ret 0
end

fn main
	# the sizes timed and checked, and the counts of threads and tasks
	store sizes 3000000
	store sizes+8 3000
	store sizes+16 3000000
	store sizes+24 3000
	store sizes+32 16777216
	store sizes+40 5000
	store threads 1
	store threads+8 2
	store threads+16 4
	store threads+24 8
	store threads+32 14
	store threads+40 20
	store sweep 20
	store sweep+8 200
	store sweep+16 2000
	store sweep+24 20000
	store sweep+32 200000
	store sweep+40 1000000
	store sweept 1
	store sweept+8 20
	store checks 1
	store checks+8 1
	store checks+16 1
	store checks+24 7
	store checks+32 2
	store checks+40 2
	store checks+48 4
	store checks+56 64
	store checks+64 20
	store checks+72 1000
	store checks+80 3
	store checks+88 5000
	call argc as n
	runs=7
	checking=0
	grids=0
	gnsel=0
	i=1
	while i<n
		call argv i as p m
		i+=1
		call same p m "--check" as r
		call same p m "--grid" as k
		if r|k
			checking|=r
			grids|=k
		else
			call same p m "--runs" as r
			if r
				call argv i as p m
				i+=1
				call num p m as runs r
				if (r==0)|(runs==0)
					call fail "usage: par [--check] [--grid] [--runs N] [name ...]"
				end
			else
				k=0
				while k<nprogs
					call item progs k as q qn
					call same p m q qn as r
					jnz r known
					k+=1
				end
				call eprint "par: no program "
				call eprint p m
				call eprint "\n"
				sys exit_group 1
				label known
				j=0
				while j<gnsel
					load sel+j*8 as r
					jeq r k dup
					j+=1
				end
				store sel+gnsel*8 k
				gnsel+=1
				label dup
			end
		end
	end
	ifz gnsel
		while gnsel<nprogs
			store sel+gnsel*8 gnsel
			gnsel+=1
		end
	end
	call filemkdir "build" 493
	call filemkdir "build/par" 493
	if checking
		call check as r
		ret r
	end
	if grids
		tail grid runs
	end
	k=0
	while k<gnsel
		load sel+k*8 as pi
		call item progs pi as np nn
		call bycut strs 0
		call build np nn "seq" as seq
		call build np nn "par" as par
		load sizes+pi*16 as n
		call args 1 n 0 0
		call run seq as t
		call keep
		call time seq runs as m
		r=rows+k*56
		store r m
		i=0
		while i<nthreads
			load threads+i*8 as th
			call args 3 n th tasks
			call time par runs as m
			store r+8+i*8 m
			i+=1
		end
		j=0
		while j<2
			load sweept+j*8 as th
			i=0
			while i<nsweep
				load sweep+i*8 as ta
				call args 3 n th ta
				call time par runs as m
				store sweeps+k*96+j*48+i*8 m
				i+=1
			end
			j+=1
		end
		k+=1
	end
	call add ob "Elapsed milliseconds, 160 tasks, median of "
	call addn ob runs
	call add ob " runs; below, the sequential time divided by it\n\n| program | sequential | 1 | 2 | 4 | 8 | 14 | 20 |\n|---|---:|---:|---:|---:|---:|---:|---:|\n"
	k=0
	while k<gnsel
		load sel+k*8 as pi
		call item progs pi as np nn
		r=rows+k*56
		load r as base
		call add ob "| "
		call add ob np nn
		call add ob " | "
		call cell base 1
		call add ob " |"
		i=0
		while i<nthreads
			load r+8+i*8 as m
			call add ob " "
			call cell m 1
			call add ob " |"
			i+=1
		end
		call add ob "\n| | |"
		i=0
		while i<nthreads
			load r+8+i*8 as m
			fdiv base m as m
			call add ob " "
			call cell m 2
			call add ob " |"
			i+=1
		end
		call add ob "\n"
		k+=1
	end
	call add ob "\nElapsed milliseconds by the number of tasks\n\n| program | threads | sequential | 20 | 200 | 2000 | 20000 | 200000 | 1000000 |\n|---|---:|---:|---:|---:|---:|---:|---:|---:|\n"
	k=0
	while k<gnsel
		load sel+k*8 as pi
		call item progs pi as np nn
		load rows+k*56 as base
		j=0
		while j<2
			load sweept+j*8 as th
			call add ob "| "
			call add ob np nn
			call add ob " | "
			call addn ob th
			call add ob " | "
			call cell base 1
			call add ob " |"
			i=0
			while i<nsweep
				load sweeps+k*96+j*48+i*8 as m
				call add ob " "
				call cell m 1
				call add ob " |"
				i+=1
			end
			call add ob "\n"
			j+=1
		end
		k+=1
	end
	call say
	call tokens
	ret 0
end

call main as x
sys exit x
