# tools/lib/o200k.aw -- the tokens of a text in the encoding o200k_base,
# as tiktoken counts them. A program that uses it uses lib/std/bytes.aw and
# lib/std/file.aw first.
#
# A text is cut into pieces, and each piece into tokens.
#
# The pieces are the matches of the pattern of the encoding, found one
# after the other; the first of these that fits at a place makes the
# piece, and a character at which none fits belongs to no piece:
#   1. one optional character that is no letter, number, CR or LF; then
#      letters and marks that are not lower case, as many as leave one
#      for what follows; then one or more that are not upper or title
#      case; then an optional 's 't 're 've 'm 'll 'd in any case
#   2. the same start; one or more letters and marks that are not lower
#      case; any that are not upper or title case; the same end
#   3. one to three numbers
#   4. an optional space; one or more characters that are no white
#      space, letter or number; then any CR, LF and /
#   5. white space up to its last CR or LF
#   6. white space but for its last character, or all of it at the end of
#      the text
#   7. white space
# A letter is a character of the general category Lu, Ll, Lt, Lm or Lo, a
# mark one of Mn, Mc or Me, a number one of Nd, Nl or No, in the Unicode
# version of the file given to tkinit; white space is the 25 characters
# with the property White_Space.
#
# The tokens of a piece: the piece itself if it is a token; else its
# bytes, of which the two neighbours that are a token together with the
# lowest rank, the first such two, become one part, until no two
# neighbours are a token. The number of a token is its rank.
#
# Every name of this file starts with "tk"; a program has no var of such
# a name (see lib/std/io.aw).

# a byte for each character, the sum of: 1 Lu, 2 Ll, 4 Lt, 8 Lm, 16 Lo,
# 32 a mark, 64 a number, 128 white space
var tkc
# the tokens, 24 bytes each: where the bytes start in tkblob, how many
# they are, the rank; their number; and the 2^19 places of 4 bytes where
# a token is found by its bytes, each the number of a token plus 1, or 0
var tkt tknt tkh
# the text at work as characters: 4 bytes each, their number, and for
# each and for the end where it starts in the text
var tkcp tkn tkof

buf tkblob 24
# the value of each byte as a digit of base64, or 255
buf tkb64 256
# the parts of a piece and the heap of its pairs, see tkbpe
buf tkst 24
buf tkhp 24
data tkdigits "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/"

fn tkhash tkp tkl
	tkx=0xcbf29ce484222325
	while tkl
		loadb tkp as tkb
		tkx=(tkx^tkb)*0x100000001b3
		tkp+=1
		tkl-=1
	end
	ret (tkx^tkx>>32)&524287
end

# the rank of the token that is the tkl bytes at tkp, or -1
fn tkrank tkp tkl
	call tkhash tkp tkl as tkx
	load tkblob as tkbl
	label next
	load4 tkh+tkx*4 as tki
	ifz tki
		ret -1
	end
	tke=tkt+tki*24-24
	load tke+8 as tkm
	if tkm==tkl
		load tke as tkq
		tkq+=tkbl
		tki=0
		while tki<tkl
			loadb tkq+tki as tka
			loadb tkp+tki as tkb
			jne tka tkb other
			tki+=1
		end
		load tke+16 as tkm
		ret tkm
	end
	label other
	tkx=tkx+1&524287
	jmp next
end

# Read the tokens from the file at the path tkmp tkmn (o200k_base.tiktoken:
# a line for each token, its bytes in base64, a space, its rank) and the
# categories of the characters from the file at tkup tkun (UnicodeData.txt
# of the Unicode Character Database). 0; or the error of reading a file,
# negated; or -1000 if a file is not what it should be.
fn tkinit tkmp tkmn tkup tkun
	local tkf 24
	store tkf 0
	store tkf+8 0
	store tkf+16 0
	alloc 1114112 as tkc
	alloc 2097152 as tkh
	alloc 4800048 as tkt
	if (tkc==0)|(tkh==0)|(tkt==0)
		ret -12
	end
	tki=0
	while tki<256
		storeb tkb64+tki 255
		tki+=1
	end
	tki=0
	while tki<64
		loadb tkdigits+tki as tkb
		storeb tkb64+tkb tki
		tki+=1
	end
	call fileread tkmp tkmn tkf as tkr
	ifslt tkr 0
		ret tkr
	end
	call byneed tkblob tkr as tkb
	ifz tkb
		ret -12
	end
	load tkf as tkp
	tke=tkp+tkr
	load tkblob as tkbl
	tko=0
	tknt=0
	while tkp<tke
		# four digits are three bytes; = fills the last four
		tks=tko
		tkv=0
		tkk=0
		label digit
		jge tkp tke bad
		loadb tkp as tkb
		tkp+=1
		if tkb!=32
			if tkb!=61
				loadb tkb64+tkb as tkb
				jeq tkb 255 bad
				tkv=tkv<<6|tkb
				tkk+=6
				if tkk>=8
					tkk-=8
					storeb tkbl+tko tkv>>tkk&255
					tko+=1
				end
			end
			jmp digit
		end
		tkv=0
		tkk=0
		label rank
		jge tkp tke bad
		loadb tkp as tkb
		tkp+=1
		if tkb!=10
			jge tkb-48 10 bad
			tkv=tkv*10+tkb-48
			tkk+=1
			jmp rank
		end
		jz tkk bad
		jge tknt 200000 bad
		jeq tko tks bad
		tkx=tkt+tknt*24
		store tkx tks
		store tkx+8 tko-tks
		store tkx+16 tkv
		tknt+=1
		call tkhash tkbl+tks tko-tks as tkx
		label place
		load4 tkh+tkx*4 as tkb
		if tkb
			tkx=tkx+1&524287
			jmp place
		end
		store4 tkh+tkx*4 tknt
	end
	store tkblob+8 tko
	# the categories: a line is the character in hexadecimal, its name, its
	# category and more, with ; between them; a name that ends with "First>"
	# starts a range that the next line ends
	call bycut tkf 0
	call fileread tkup tkun tkf as tkr
	ifslt tkr 0
		ret tkr
	end
	load tkf as tkp
	tke=tkp+tkr
	tkfirst=-1
	while tkp<tke
		tkv=0
		tkk=0
		label hex
		jge tkp tke bad
		loadb tkp as tkb
		tkp+=1
		if tkb!=59
			tka=tkb-48
			if tka>=10
				tka=(tkb|32)-87
				jlt tka 10 bad
				jge tka 16 bad
			end
			tkv=tkv<<4|tka
			tkk+=1
			jmp hex
		end
		jz tkk bad
		jge tkv 1114112 bad
		# the name, and whether it ends with "First>"
		tks=tkp
		label name
		jge tkp tke bad
		loadb tkp as tkb
		tkp+=1
		jne tkb 59 name
		tkx=0
		if tkp-tks>7
			load tkp-7 as tkx
			tkx=(tkx&0xffffffffffff)==0x3e7473726946
		end
		jge tkp+2 tke bad
		loadb tkp as tka
		loadb tkp+1 as tkb
		tkm=0
		if tka==76
			if tkb==117
				tkm=1
			end
			if tkb==108
				tkm=2
			end
			if tkb==116
				tkm=4
			end
			if tkb==109
				tkm=8
			end
			if tkb==111
				tkm=16
			end
		end
		if tka==77
			tkm=32
		end
		if tka==78
			tkm=64
		end
		if tkx
			tkfirst=tkv
		else
			if tkfirst==-1
				tkfirst=tkv
			end
			jgt tkfirst tkv bad
			while tkfirst<=tkv
				storeb tkc+tkfirst tkm
				tkfirst+=1
			end
			tkfirst=-1
		end
		label line
		jge tkp tke eof
		loadb tkp as tkb
		tkp+=1
		jne tkb 10 line
		label eof
	end
	call byfree tkf
	# white space
	tki=9
	while tki<14
		storeb tkc+tki 128
		tki+=1
	end
	storeb tkc+32 128
	storeb tkc+0x85 128
	storeb tkc+0xa0 128
	storeb tkc+0x1680 128
	tki=0x2000
	while tki<0x200b
		storeb tkc+tki 128
		tki+=1
	end
	storeb tkc+0x2028 128
	storeb tkc+0x2029 128
	storeb tkc+0x202f 128
	storeb tkc+0x205f 128
	storeb tkc+0x3000 128
	ret 0
	label bad
	ret -1000
end

# the place after an 's 't 're 've 'm 'll or 'd at the place tkp, in any
# case, where the long s is an s too; or tkp
fn tkcontr tkp
	if tkp+1<tkn
		load4 tkcp+tkp*4 as tka
		if tka==39
			load4 tkcp+tkp*4+4 as tka
			tkb=0
			if tkp+2<tkn
				load4 tkcp+tkp*4+8 as tkb
			end
			tkb|=32
			if ((tka|32)==115)|(tka==0x17f)|((tka|32)==116)|((tka|32)==109)|((tka|32)==100)
				ret tkp+2
			end
			if (((tka|32)==114)|((tka|32)==118))&(tkb==101)
				ret tkp+3
			end
			if ((tka|32)==108)&(tkb==108)
				ret tkp+3
			end
		end
	end
	ret tkp
end

# the end of a word that starts at tkp by the first rule, or -1: the
# characters that are not lower case give back as many as it takes for
# one that is not upper case to follow
fn tkword1 tkp
	tkq=tkp
	while tkq<tkn
		load4 tkcp+tkq*4 as tka
		loadb tkc+tka as tka
		jz tka&61 run
		tkq+=1
	end
	label run
	label back
	if tkq<tkn
		load4 tkcp+tkq*4 as tka
		loadb tkc+tka as tka
		if tka&58
			while tkq<tkn
				load4 tkcp+tkq*4 as tka
				loadb tkc+tka as tka
				jz tka&58 done
				tkq+=1
			end
			label done
			tail tkcontr tkq
		end
	end
	jeq tkq tkp no
	tkq-=1
	jmp back
	label no
	ret -1
end

# the same by the second rule
fn tkword2 tkp
	tkq=tkp
	while tkq<tkn
		load4 tkcp+tkq*4 as tka
		loadb tkc+tka as tka
		jz tka&61 run
		tkq+=1
	end
	label run
	if tkq==tkp
		ret -1
	end
	while tkq<tkn
		load4 tkcp+tkq*4 as tka
		loadb tkc+tka as tka
		jz tka&58 done
		tkq+=1
	end
	label done
	tail tkcontr tkq
end

# the end of the piece that starts at the character tki, or -1
fn tkpiece tki
	load4 tkcp+tki*4 as tkx
	loadb tkc+tkx as tkm
	# a word, with one character before it that is no letter, number, CR or LF
	tka=(tkx!=13)&(tkx!=10)&((tkm&95)==0)
	if tka
		call tkword1 tki+1 as tke
		jne tke -1 found
	end
	call tkword1 tki as tke
	jne tke -1 found
	if tka
		call tkword2 tki+1 as tke
		jne tke -1 found
	end
	call tkword2 tki as tke
	jne tke -1 found
	if tkm&64
		tke=tki+1
		while (tke<tkn)&(tke<tki+3)
			load4 tkcp+tke*4 as tkx
			loadb tkc+tkx as tkb
			jz tkb&64 found
			tke+=1
		end
		jmp found
	end
	# characters that are no white space, letter or number
	tke=tki
	if tkx==32
		tke+=1
	end
	if tke<tkn
		load4 tkcp+tke*4 as tka
		loadb tkc+tka as tkb
		ifz tkb&223
			while tke<tkn
				load4 tkcp+tke*4 as tka
				loadb tkc+tka as tkb
				jnz tkb&223 tailing
				tke+=1
			end
			label tailing
			while tke<tkn
				load4 tkcp+tke*4 as tka
				jz (tka==13)|(tka==10)|(tka==47) found
				tke+=1
			end
			jmp found
		end
	end
	ifz tkm&128
		ret -1
	end
	# white space: tke its end, tkq after its last CR or LF
	tke=tki
	tkq=0
	while tke<tkn
		load4 tkcp+tke*4 as tka
		loadb tkc+tka as tkb
		jz tkb&128 wsend
		tke+=1
		if (tka==13)|(tka==10)
			tkq=tke
		end
	end
	label wsend
	if tkq
		ret tkq
	end
	if (tke<tkn)&(tke-tki>=2)
		tke-=1
	end
	label found
	ret tke
end

# The heap of the pairs of a piece: 16 bytes each in tkhp, the rank of a
# pair and the part it starts with, the lowest rank first and of one rank
# the first part. tkheap adds one; tkpop takes the first away.
fn tkheap tkr tki
	load tkhp as tkp
	load tkhp+8 as tkl
	tkj=tkl/16
	store tkhp+8 tkl+16
	while tkj
		tkk=(tkj-1)/2
		load tkp+tkk*16 as tka
		load tkp+tkk*16+8 as tkb
		jz (tkr<tka)|((tkr==tka)&(tki<tkb)) put
		store tkp+tkj*16 tka
		store tkp+tkj*16+8 tkb
		tkj=tkk
	end
	label put
	store tkp+tkj*16 tkr
	store tkp+tkj*16+8 tki
end
fn tkpop
	load tkhp as tkp
	load tkhp+8 as tkl
	tkl=tkl/16-1
	store tkhp+8 tkl*16
	load tkp+tkl*16 as tkr
	load tkp+tkl*16+8 as tki
	tkj=0
	label down
	tkk=tkj*2+1
	if tkk<tkl
		load tkp+tkk*16 as tka
		load tkp+tkk*16+8 as tkb
		if tkk+1<tkl
			load tkp+tkk*16+16 as tkx
			load tkp+tkk*16+24 as tkd
			if (tkx<tka)|((tkx==tka)&(tkd<tkb))
				tkk+=1
				tka=tkx
				tkb=tkd
			end
		end
		if (tka<tkr)|((tka==tkr)&(tkb<tki))
			store tkp+tkj*16 tka
			store tkp+tkj*16+8 tkb
			tkj=tkk
			jmp down
		end
	end
	store tkp+tkj*16 tkr
	store tkp+tkj*16+8 tki
end

# the tokens of the piece of tkl bytes at tkp: their number; their ranks
# are added to the buffer tko, 8 bytes each, if it is not 0.
# A part is named by the byte it starts with. tkst has for each byte 24
# bytes: the part after the one that starts there (tkl for none), the
# part before it, and the rank of the two together, -1 if they are no
# token or the byte starts no part any more. A pair in the heap is good
# while that rank is still its own.
fn tkbpe tkp tkl tko
	call tkrank tkp tkl as tkr
	if tkr!=-1
		if tko
			call tkput tko tkr as tka
			jz tka nomem
		end
		ret 1
	end
	call bycut tkst 0
	call bycut tkhp 0
	call byneed tkst tkl*24+24 as tka
	call byneed tkhp tkl*48+48 as tkb
	jz tka&tkb nomem
	load tkst as tks
	tki=0
	while tki<tkl
		tke=tks+tki*24
		store tke tki+1
		store tke+8 tki-1
		tkr=-1
		if tki+1<tkl
			call tkrank tkp+tki 2 as tkr
			if tkr!=-1
				call tkheap tkr tki
			end
		end
		store tke+16 tkr
		tki+=1
	end
	tkk=tkl
	label merge
	load tkhp+8 as tka
	if tka
		load tkhp as tka
		load tka as tkr
		load tka+8 as tki
		call tkpop
		tke=tks+tki*24
		load tke+16 as tka
		jne tka tkr merge
		# the part tki takes the one after it, tkj; tkx is the part after that
		load tke as tkj
		load tks+tkj*24 as tkx
		store tks+tkj*24+16 -1
		store tke tkx
		tkk-=1
		tkr=-1
		if tkx<tkl
			store tks+tkx*24+8 tki
			load tks+tkx*24 as tkb
			call tkrank tkp+tki tkb-tki as tkr
			if tkr!=-1
				call tkheap tkr tki
			end
		end
		store tke+16 tkr
		load tke+8 as tkj
		if tkj!=-1
			call tkrank tkp+tkj tkx-tkj as tkr
			store tks+tkj*24+16 tkr
			if tkr!=-1
				call tkheap tkr tkj
			end
		end
		jmp merge
	end
	if tko
		tki=0
		while tki<tkl
			load tks+tki*24 as tkj
			call tkrank tkp+tki tkj-tki as tkr
			call tkput tko tkr as tka
			jz tka nomem
			tki=tkj
		end
	end
	ret tkk
	label nomem
	ret -12
end

# add the 8 bytes of tkv to the buffer tko: 1, or 0 without memory
fn tkput tko tkv
	call byneed tko 8 as tka
	ifz tka
		ret 0
	end
	load tko as tkp
	load tko+8 as tkl
	store tkp+tkl tkv
	store tko+8 tkl+8
	ret 1
end

# The tokens of the tkl bytes at tkp, which are text in UTF-8: their
# number; or -1 if the bytes are no UTF-8; or -12 without memory. If tko
# is not 0 it is a buffer of lib/std/bytes.aw, to which the rank of every
# token is added, 8 bytes each. tkinit was called before.
fn tkencode tkp tkl tko
	alloc tkl*4+8 as tkcp
	alloc tkl*8+16 as tkof
	if (tkcp==0)|(tkof==0)
		ret -12
	end
	# the characters; a byte sequence longer than needed, a surrogate and a
	# number above 0x10ffff are no UTF-8
	tkn=0
	tki=0
	while tki<tkl
		loadb tkp+tki as tka
		tkk=0
		tkm=0
		if tka>=128
			jlt tka 192 bad
			jge tka 248 bad
			tkk=1
			tkm=128
			if tka>=224
				tkk=2
				tkm=0x800
			end
			if tka>=240
				tkk=3
				tkm=0x10000
			end
			tka&=127>>tkk+1
		end
		jgt tki+tkk+1 tkl bad
		tkj=1
		while tkj<=tkk
			loadb tkp+tki+tkj as tkb
			jne tkb&192 128 bad
			tka=tka<<6|tkb&63
			tkj+=1
		end
		jlt tka tkm bad
		jge tka 1114112 bad
		jlt tka-0xd800 0x800 bad
		store4 tkcp+tkn*4 tka
		store tkof+tkn*8 tki
		tkn+=1
		tki+=tkk+1
	end
	store tkof+tkn*8 tkl
	tkr=0
	tki=0
	while tki<tkn
		call tkpiece tki as tke
		if tke==-1
			tki+=1
		else
			load tkof+tki*8 as tka
			load tkof+tke*8 as tkb
			call tkbpe tkp+tka tkb-tka tko as tkk
			ifslt tkk 0
				tkr=tkk
				tki=tkn
			else
				tkr+=tkk
				tki=tke
			end
		end
	end
	free tkcp
	free tkof
	ret tkr
	label bad
	free tkcp
	free tkof
	ret -1
end
