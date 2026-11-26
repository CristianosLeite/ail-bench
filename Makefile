# The benchmarks and measurements of AIL. The compiler, the libraries and
# the ten small examples are those of the main repository, which is at
# ail/: a clone of the address and revision in ail.pin, which the first
# make fetches, or a link to a checkout named once with make AIL=<path>.
# Nothing of it is copied here.
B := build

# tools/ail.sh puts the repository there and makes the links into it
# whenever make reads this file.
AILSAID := $(shell tools/ail.sh $(AIL) 2>&1 || echo failed)
ifneq ($(AILSAID),)
$(error $(AILSAID))
endif

# The programs that are examples/<name>.aiw of the main repository, which
# C, Rust and assembly mirror in bench/, and the algorithm programs, whose
# AIL source is bench/<name>.aiw beside the two others.
SMALL := hello echo count loop fact compute sieve collatz fnv isort
ALGO := quicksort mergesort heapsort bsearch hashtab graph ndelem ndreduce \
        ndmatmul collseq collmap
# The programs that spend their time in the runtime of AIL: reading and
# writing, and memory. bench/<name>.aiw and the C program beside it.
RT := iocopy iosmall allocs

TOOLS := $(patsubst tools/%.aiw,$(B)/tools/%,$(wildcard tools/*.aiw))

all: $(patsubst %,$(B)/%,$(SMALL) $(ALGO) $(RT)) $(TOOLS)

tools: $(TOOLS)

# The compiler and the formatter are built where their source is and
# linked here, since the tools name build/ailc. A link has the time of
# its file, so a new compiler builds everything here again. The recipe
# names make by itself, so that make -n, which tests/tools.sh reads the
# rules with, does not run it.
$(B)/ailc: FORCE
	@make -s -C ail AIL= build/ailc build/tools/indent
	@mkdir -p $(B)/tools
	@ln -sfn ../ail/build/ailc $(B)/ailc
	@ln -sfn ../../ail/build/tools/indent $(B)/tools/indent

FORCE:

# $(call compile,<compiler>): compile $< to $@.
# Build into a temporary file so a failed compile never leaves a target
# that looks up to date.
define compile
@echo "$(1) < $< > $@"
@tmp=$$(mktemp $@.tmp.XXXXXX) || exit 1; \
trap 'rm -f "$$tmp"' EXIT; trap 'exit 1' HUP INT TERM; \
$(1) < $< > "$$tmp" && chmod 755 "$$tmp" && mv "$$tmp" $@
endef

$(B)/%: bench/%.aiw $(B)/ailc
	$(call compile,$(B)/ailc)

$(B)/%: ail/examples/%.aiw $(B)/ailc
	$(call compile,$(B)/ailc)

# The algorithm programs name their harness with use, so they are built
# again when it changes.
$(patsubst %,$(B)/%,$(ALGO)): bench/algo/harness.aiw

# Their kernels are files of lib/algo/.
$(patsubst %,$(B)/%,quicksort mergesort heapsort isort): lib/algo/sort.aiw
$(B)/bsearch: lib/algo/search.aiw
$(B)/hashtab: lib/algo/hash.aiw
$(B)/graph: lib/algo/graph.aiw

# The collection programs name the collections.
$(B)/collseq: lib/std/vec.aiw lib/std/queue.aiw lib/std/heap.aiw
$(B)/collmap: lib/std/map.aiw

# The array programs also name the library of arrays.
$(patsubst %,$(B)/%,ndelem ndreduce ndmatmul): lib/nd/array.aiw lib/nd/elem.aiw
$(patsubst %,$(B)/%,ndreduce ndmatmul): lib/nd/reduce.aiw

# The tools: tools/<name>.aiw is build/tools/<name>. Each file a tool names
# with use is a prerequisite in its line below, which tests/tools.sh
# compares with the use statements. The source of the tool stays the first
# prerequisite, the one the recipe compiles.
$(B)/tools/%: tools/%.aiw $(B)/ailc
	@mkdir -p $(@D)
	$(call compile,$(B)/ailc)

TL := lib/std/io.aiw lib/std/bytes.aiw lib/std/file.aiw

$(B)/tools/perf: $(TL) lib/std/proc.aiw tools/lib/rng.aiw tools/lib/fmt.aiw
$(B)/tools/tokens: $(TL) tools/lib/fmt.aiw tools/lib/o200k.aiw
$(B)/tools/par: $(TL) lib/std/proc.aiw tools/lib/fmt.aiw
$(B)/tools/nptime: $(TL) lib/std/proc.aiw tools/lib/fmt.aiw

test: all
ifeq ($(T),)
	@f=0; AILC=$(B)/ailc tests/run.sh || f=1; tests/par.sh || f=1; \
	AILC=$(B)/ailc tests/async.sh || f=1; tests/algo.sh || f=1; \
	tests/tools.sh || f=1; tests/tokens.sh || f=1; tests/indent.sh || f=1; exit $$f
else
	@AILC=$(B)/ailc tests/run.sh $(T)
endif

clean:
	rm -rf $(B)

.PHONY: all tools test clean FORCE
