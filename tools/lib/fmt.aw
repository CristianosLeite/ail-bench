# tools/lib/fmt.aw -- numbers as text for the tools.
# A program that uses it uses lib/std/bytes.aw first.
#
# Every name of this file starts with "fmt"; a program has no var of such
# a name (see lib/std/io.aw).

# Add the double fmtx to the buffer fmtb as a decimal number with fmtd
# digits after the point, 0 to 3 of them: the nearest such number to the
# exact value of the double, the even one of two that are equally near,
# which is what "%.2f" of C and Python print. The double is given as its
# 64 bits, as itof and fdiv leave it, and is below 2^53 in size; an
# infinity or a NaN is written as "inf" or "nan". 1, or 0 without memory.
fn fmtfix fmtb fmtx fmtd
	fmte=fmtx>>52&2047
	fmtm=fmtx&0xfffffffffffff
	fmtr=1
	if fmtx>>63
		call byaddb fmtb 45 as fmtr
	end
	if fmte==2047
		ifz fmtm
			call byadd fmtb "inf" as fmtq
		else
			call byadd fmtb "nan" as fmtq
		end
		ret fmtr&fmtq
	end
	# the value is fmtm times 2 to the power fmte, and fmtp is 10^fmtd
	if fmte
		fmtm|=0x10000000000000
		fmte-=1075
	else
		fmte=-1074
	end
	fmtp=1
	fmti=0
	while fmti<fmtd
		fmtp*=10
		fmti+=1
	end
	ifsge fmte 0
		# a whole number
		fmtq=fmtm<<fmte
		fmtq*=fmtp
	else
		fmtk=0-fmte
		fmtn=fmtm*fmtp
		if fmtk>=64
			fmtq=0
		else
			fmtq=fmtn>>fmtk
			fmth=1<<(fmtk-1)
			fmtn&=(fmth<<1)-1
			if (fmtn>fmth)|((fmtn==fmth)&(fmtq&1))
				fmtq+=1
			end
		end
	end
	call byaddn fmtb fmtq/fmtp as fmtk
	fmtr&=fmtk
	if fmtd
		call byaddb fmtb 46 as fmtk
		fmtr&=fmtk
		fmtq%=fmtp
		while fmtd
			fmtp/=10
			call byaddb fmtb 48+fmtq/fmtp%10 as fmtk
			fmtr&=fmtk
			fmtd-=1
		end
	end
	ret fmtr
end
