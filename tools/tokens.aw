# tools/tokens.aw -- count the tokens of each benchmark program in each
# language.
#
#   build/tools/tokens [name ...]        (default: every program)
#   build/tools/tokens -c <mark> file ...
#   build/tools/tokens --ids file
#   build/tools/tokens --code <mark> file
#
# The AIL source is bench/<name>.aw or, for a program that is an example
# of the main repository, ail/examples/<name>.aw; the others are
# bench/<name>.<ext>.
# Comments and blank lines are removed first, so only code is counted. A
# program without a source in a language has an empty cell there, and
# that language is compared with the AIL total of the programs it has.
# The table is Markdown, on stdout.
#
# With -c the tokens of each file are counted, without its comments, which
# start with the mark ("#", "//") and run to the end of the line, and
# without its blank lines: a line "<count> <file>" for each. With --ids
# the numbers of the tokens of the file as it is are printed, one a line.
# With --code the text that -c counts is printed, the file without its
# comments and blank lines, for a count by another tokenizer
# (tools/claudetokens.sh).
#
# The tokens are those of the encoding o200k_base (tools/lib/o200k.aw),
# from the two files that tools/pins/MANIFEST pins; tools/pin.sh fetches
# them. A text is UTF-8. Run from the top of the repository. Status 0, or
# 1 with a line "tokens: ..." on stderr.
use "lib/std/io.aw"
use "lib/std/bytes.aw"
use "lib/std/file.aw"
use "tools/lib/fmt.aw"
use "tools/lib/o200k.aw"

# the languages: the name, what stands before and after the name of a
# program in the path of its source, and the mark of a comment
data langs 3 "AIL" 1 "C" 4 "Rust" 3 "asm"
data dirs 6 "bench/" 6 "bench/" 6 "bench/" 6 "bench/"
data exts 3 ".aw" 2 ".c" 3 ".rs" 2 ".s"
data marks 1 "#" 2 "//" 2 "//" 1 "#"

# a file as it is, its code, a path, a line for stdout, the names of the
# examples, and the numbers of the tokens
buf raw 24
buf src 24
buf path 24
buf ob 24
buf names 24
buf ids 24

fn fail3 a an b bn c cn
	call eprint "tokens: "
	call eprint a an
	call eprint b bn
	call eprint c cn
	call eprint "\n"
	sys exit_group 1
end

# 0 is no memory
fn must r
	ifz r
		call fail3 "out of memory" "" ""
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

# entry i of a list in which each entry is its length in a byte and then
# its bytes: its address and length
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

# the length of the white space character at p in a line that ends at e,
# or 0 if there is none: what Python takes for white space in a text,
# the characters with the property White_Space and 0x1c to 0x1f
fn wslen p e
	loadb p as c
	if (c-9<5)|(c-28<5)
		ret 1
	end
	if e-p>=2
		loadb p+1 as d
		if (c==0xc2)&((d==0x85)|(d==0xa0))
			ret 2
		end
		if e-p>=3
			loadb p+2 as f
			if (c==0xe1)&(d==0x9a)&(f==0x80)
				ret 3
			end
			if (c==0xe2)&(d==0x80)&((f-0x80<11)|(f==0xa8)|(f==0xa9)|(f==0xaf))
				ret 3
			end
			if (c==0xe2)&(d==0x81)&(f==0x9f)
				ret 3
			end
			if (c==0xe3)&(d==0x80)&(f==0x80)
				ret 3
			end
		end
	end
end

# the code of the n bytes at p into src: every line without its comment,
# which is the mark of mn bytes at m and what follows, with the white
# space before it; the lines that have more than white space left, each
# with a LF after it. A line ends at LF, CR or CR LF.
fn code p n m mn
	call bycut src 0
	e=p+n
	while p<e
		# q the end of the line, r the start of the next
		q=p
		while q<e
			loadb q as c
			jeq c 10 eol
			jeq c 13 eol
			q+=1
		end
		label eol
		r=q
		if q<e
			r=q+1
			loadb q as c
			if (c==13)&(r<e)
				loadb r as c
				if c==10
					r+=1
				end
			end
		end
		# k the end of what is kept: after the last character that is no white
		# space, as long as no mark was met
		k=p
		i=p
		while i<q
			if q-i>=mn
				call same i m mn as c
				jnz c cut
			end
			call wslen i q as c
			if c
				i+=c
			else
				i+=1
				k=i
			end
		end
		label cut
		if k!=p
			call add src p k-p
			call add src "\n"
		end
		p=r
	end
	load src+8 as n
	ifz n
		call add src "\n"
	end
end

# 1 if the n bytes at p and at q are the same
fn same p q n
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

# the file at the path into raw: 1, or 0 if there is no such file; any
# other error ends the program
fn readraw p n
	call bycut raw 0
	call fileread p n raw as r
	if r==-2
		ret 0
	end
	ifslt r 0
		call fail3 "cannot read " p n ""
	end
	ret 1
end

# the number of tokens of the n bytes at p, which came from the file fp fn
fn count p n fp fn
	call tkencode p n 0 as r
	if r==-1
		call fail3 "" fp fn " is not UTF-8"
	end
	ifslt r 0
		call fail3 "out of memory" "" ""
	end
	ret r
end

# the table of the programs whose names, each with a NUL byte after it,
# are the nn names at np
fn table np nn
	local total 32
	local base 32
	i=0
	while i<4
		store total+i*8 0
		store base+i*8 0
		i+=1
	end
	call add ob "| program | AIL | C | Rust | asm |\n|---|---:|---:|---:|---:|\n"
	call say
	while nn
		call strlen np as nl
		call add ob "| "
		call add ob np nl
		call add ob " | "
		ail=0
		i=0
		while i<4
			if i
				call add ob " | "
			end
			call bycut path 0
			call item dirs i as q m
			call add path q m
			call add path np nl
			call item exts i as q m
			call add path q m
			load path as fp
			load path+8 as fn
			call readraw fp fn as r
			ifz r|i
				call bycut path 0
				call add path "ail/examples/"
				call add path np nl
				call add path ".aw"
				load path as fp
				load path+8 as fn
				call readraw fp fn as r
			end
			if r
				load raw as p
				load raw+8 as n
				call item marks i as q m
				call code p n q m
				load src as p
				load src+8 as n
				call count p n fp fn as n
				ifz i
					ail=n
				end
				load total+i*8 as t
				store total+i*8 t+n
				load base+i*8 as t
				store base+i*8 t+ail
				call addn ob n
			end
			i+=1
		end
		call add ob " |\n"
		call say
		np+=nl+1
		nn-=1
	end
	call add ob "| total | "
	i=0
	while i<4
		if i
			call add ob " | "
		end
		load total+i*8 as t
		call addn ob t
		i+=1
	end
	call add ob " |\n| relative to AIL | "
	i=0
	while i<4
		if i
			call add ob " | "
		end
		load total+i*8 as t
		load base+i*8 as b
		itof t as t
		itof b as b
		fdiv t b as t
		call fmtfix ob t 2 as r
		call must r
		i+=1
	end
	call add ob " |\n"
	call say
end

# the names of the programs, which are the AIL sources of bench/ and of
# the examples of the main repository, in the order of their bytes, into
# names: their number
fn examples
	local all 24
	store all 0
	store all+8 0
	store all+16 0
	call filenames "ail/examples" all as n
	ifslt n 0
		call fail3 "cannot read ail/examples" "" ""
	end
	call filenames "bench" all as r
	ifslt r 0
		call fail3 "cannot read bench" "" ""
	end
	n+=r
	# the ones that end with .aw, without it, as addresses
	alloc n*8+8 as v
	call must v
	load all as p
	k=0
	while n
		call strlen p as l
		if l>=3
			call same p+l-3 ".aw" as r
			if r
				storeb p+l-3 0
				store v+k*8 p
				k+=1
			end
		end
		p+=l+1
		n-=1
	end
	i=1
	while i<k
		load v+i*8 as p
		j=i
		while j
			load v+j*8-8 as q
			call before p q as r
			jz r put
			store v+j*8 q
			j-=1
		end
		label put
		store v+j*8 p
		i+=1
	end
	i=0
	while i<k
		load v+i*8 as p
		call strlen p as l
		call add names p l+1
		i+=1
	end
	ret k
end

# 1 if the string at p comes before the one at q, byte by byte
fn before p q
	label next
	loadb p as c
	loadb q as d
	if c!=d
		ret c<d
	end
	ifz c
		ret 0
	end
	p+=1
	q+=1
	jmp next
end

fn main
	call argc as n
	# the code of a file as it is counted needs no table of tokens
	if n>1
		call argv 1 as p m
		if m==6
			call same p "--code" as r
			if r
				if n!=4
					call fail3 "usage: tokens --code <mark> file" "" ""
				end
				call argv 2 as mp mn
				call argv 3 as fp fn
				call readraw fp fn as r
				ifz r
					call fail3 "cannot read " fp fn ""
				end
				load raw as p
				load raw+8 as m
				call code p m mp mn
				load src as p
				load src+8 as m
				call add ob p m
				call say
				ret 0
			end
		end
	end
	call tkinit "tools/pins/o200k_base.tiktoken" "tools/pins/UnicodeData.txt" as r
	ifslt r 0
		call fail3 "cannot use tools/pins/o200k_base.tiktoken and tools/pins/UnicodeData.txt;" " run tools/pin.sh" " o200k_base.tiktoken UnicodeData.txt"
	end
	call argv 1 as p m
	if m==2
		call same p "-c" as r
		if r
			if n<4
				call fail3 "usage: tokens -c <mark> file ..." "" ""
			end
			call argv 2 as mp mn
			i=3
			while i<n
				call argv i as fp fn
				call readraw fp fn as r
				ifz r
					call fail3 "cannot read " fp fn ""
				end
				load raw as p
				load raw+8 as m
				call code p m mp mn
				load src as p
				load src+8 as m
				call count p m fp fn as r
				call addn ob r
				call add ob " "
				call add ob fp fn
				call add ob "\n"
				call say
				i+=1
			end
			ret 0
		end
	end
	if m==5
		call same p "--ids" as r
		if r
			if n!=3
				call fail3 "usage: tokens --ids file" "" ""
			end
			call argv 2 as fp fn
			call readraw fp fn as r
			ifz r
				call fail3 "cannot read " fp fn ""
			end
			load raw as p
			load raw+8 as m
			call tkencode p m ids as r
			if r==-1
				call fail3 "" fp fn " is not UTF-8"
			end
			ifslt r 0
				call fail3 "out of memory" "" ""
			end
			load ids as p
			i=0
			while i<r
				load p+i*8 as v
				call addn ob v
				call add ob "\n"
				i+=1
			end
			call say
			ret 0
		end
	end
	if n>1
		i=1
		while i<n
			call argv i as p m
			call add names p m+1
			i+=1
		end
		load names as p
		call table p n-1
		ret 0
	end
	call examples as k
	load names as p
	call table p k
	ret 0
end

call main
