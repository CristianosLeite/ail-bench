# Performance

> These measurements were made while the programs were part of the [main repository](https://github.com/CristianosLeite/ail), until 2026-11-21. In the sections written before that day `examples/` is `examples/` of the main repository for the ten small programs and `bench/<name>.aw` here for the algorithm, array and collection programs, `docs/language.md` and `docs/compiler.md` are documents there, and the tests named `lib_*` and `tests/nd/gen.py` are there too.

`tools/perf.aw`, built as `build/tools/perf` by `make tools`, times every example against the C, Rust and assembly program in `bench/` that does the same. It is the measurement behind the performance goal of the redesign: elapsed time on CPU-bound programs close to C built with `-O3`. For the six algorithm programs it also reports the time of the kernel alone, on several inputs each ([below](#algorithms-2026-11-20)).

```
make all tools
build/tools/perf              # every program
build/tools/perf compute      # some programs only
build/tools/perf --quick      # few rounds, to see that it runs
build/tools/perf --cpu 4      # another CPU than 2
build/tools/perf --qsort      # the sorts against the qsort of the C library too
```

## Method

- C is built with `gcc -O3`, Rust with `rustc -O`, assembly with `as` and `ld`; the AIL program is `build/<name>`, so run `make` first. A language without a source for a program is left out of its row.
- Every executable must give the expected output and exit status for the input before anything is timed. Where the output is a computed value, all languages must give the same bytes as C.
- A run is a child process of `lib/std/proc.aw` and the wait for it (`fork`, `dup2`, `execve`, `wait4`), timed with the monotonic clock, with stdin from a file and stdout to `/dev/null`, the tool pinned to one CPU. Until 2026-11-20 the tool was a Python script that started the child with `posix_spawn`; [the two are compared below](#the-measuring-tool-in-ail-2026-11-20). Each round runs every language once, in an order shuffled from a fixed seed, so that drift of the machine falls on all of them alike.
- The figure is the median, followed by the first and third quartile, which show the variation from run to run.
- The input is read at run time, so no compiler can compute the result while compiling.

The programs are of two kinds, reported apart:

- **Startup**: `hello`, `echo`, `count`, `fact` and `loop` do almost nothing, so their time is the cost of starting a process. 25 warm-up rounds and 500 timed ones, in microseconds.
- **CPU-bound**: five programs that read their input, compute and write a 64-bit result. 2 warm-up rounds and 15 timed ones, in milliseconds. For these the table gives the ratio of AIL to C and, below it, the geometric mean of the ratios; a ratio above 1.10 is marked `slow`.

- **Algorithms**: `quicksort`, `mergesort`, `heapsort`, `bsearch`, `hashtab` and `graph` are a kernel each and its call of `bench/algo/harness.aw`. They are run on several inputs, take the time of the kernel themselves and are reported in tables of their own; [the section below](#algorithms-2026-11-20) says how. The same rounds as the CPU-bound programs.

| program | what it does | what it exercises |
|---|---|---|
| `compute` | a hundred million steps of `x = (x ^ (x >> 13)) * 1664525 + 1013904223` | arithmetic in one dependent chain |
| `sieve` | counts the primes below 16000000 in a table of bytes | byte stores at a stride, a nested loop |
| `collatz` | adds up the lengths of the Collatz sequences from 1 to 500000 | a branch that cannot be predicted, `% 2`, `/ 2`, `* 3` |
| `fnv` | hashes a megabyte a hundred times with FNV-1a | byte loads, a multiplication per byte |
| `isort` | sorts 30000 numbers by insertion | 64-bit loads and stores, a comparison per step |

The last four have no assembly equivalent, and neither have the algorithm programs. At the baseline below the four were written as the language then was, one operation a line with labels and jumps, and at top level with at most six variables, since a seventh would live in memory there.

## Baseline, 2026-11-20

12th Gen Intel Core i7-12700H, CPU 2 (a performance core), Linux 7.0; gcc 15.2.0, rustc 1.99.0.

Startup, microseconds:

| program | AIL | C | Rust | asm |
|---|---:|---:|---:|---:|
| hello | 74.66 (65.37, 80.18) | 232.38 (222.04, 241.62) | 320.15 (309.77, 336.92) | 75.53 (67.61, 81.14) |
| echo | 76.67 (68.32, 83.16) | 229.88 (218.91, 241.64) | 316.27 (304.98, 335.08) | 74.43 (66.48, 81.54) |
| count | 79.57 (70.92, 85.16) | 233.25 (225.81, 242.72) | 323.65 (316.54, 334.15) | 77.70 (69.66, 83.83) |
| fact | 74.44 (65.55, 79.72) | 224.76 (219.08, 233.25) | 313.32 (308.01, 320.15) | 74.28 (64.93, 79.14) |
| loop | 79.44 (70.12, 85.42) | 231.97 (222.72, 239.93) | 323.82 (311.43, 332.38) | 78.31 (71.04, 83.72) |

An AIL program starts in a third of the time of the C one and a quarter of the Rust one, like the assembly program: it is a static file of a few hundred bytes with no loader and no runtime to set up.

CPU-bound, milliseconds:

| program | AIL | C | Rust | asm | AIL / C |
|---|---:|---:|---:|---:|---:|
| compute | 134.38 (133.89, 134.75) | 131.70 (131.59, 131.81) | 131.90 (131.78, 132.73) | 131.42 (131.32, 131.94) | 1.020 |
| sieve | 60.77 (60.46, 61.51) | 50.82 (50.62, 51.40) | 52.41 (51.95, 52.97) |  | 1.196 slow |
| collatz | 325.56 (325.21, 325.86) | 77.92 (77.80, 78.49) | 57.66 (57.54, 57.76) |  | 4.178 slow |
| fnv | 92.76 (92.43, 93.21) | 92.73 (92.60, 93.20) | 93.01 (92.84, 93.51) |  | 1.000 |
| isort | 158.28 (158.11, 158.94) | 50.36 (50.25, 50.59) | 70.79 (70.63, 71.08) |  | 3.143 slow |

Geometric mean of AIL / C: 1.742.

What the figures say about the code the compiler writes today:

- `compute` and `fnv` are within 2% of C although AIL runs three times the instructions: the loop of `compute` is 22 against 7, every operand copied to `rax` or `rdi`, `13` and the two constants loaded on every step, and `jnz` testing its value again. The steps depend on each other, so the time is the latency of the shift, the multiplication and the additions in a row, and the extra moves run beside them. One program of that shape had made the compiler look finished.
- `collatz` is four times slower. `mod x 2` and `div x 2` are a 64-bit `div` each, where C tests a bit and shifts; and C computes `3x + 1` with one `lea` and folds the halving that must follow an odd step into it.
- `isort` is three times slower: its inner loop has nothing but moves, a load, a store, a comparison and two jumps, so the copies through `rax` and `rdi` and the jump to the test at the top are the whole cost.
- `sieve` is 20% slower for the same reason, less so because its inner loop waits for memory.

At that point the work was in three places: a constant operand used as an immediate, with division and remainder by a power of two as a shift and a mask; operands used where they are instead of copied; and a loop with its test at the end.

## After instruction selection, 2026-11-20

Same machine, same compilers, and the same sources of the examples, still written with one operation a line, labels and jumps. What changed is the compiler: immediate operands, variables used where they live, an assignment computed in the register of its variable, statements compiled like the expressions that say the same, a test left out behind the instruction that computed its value, and loads and stores with the address as the operand of the instruction.

| program | AIL | C | Rust | asm | AIL / C | before |
|---|---:|---:|---:|---:|---:|---:|
| compute | 131.72 (131.52, 132.47) | 131.79 (131.71, 132.16) | 132.02 (131.83, 132.46) | 131.58 (131.51, 132.10) | 0.999 | 1.020 |
| sieve | 53.01 (52.81, 53.69) | 50.91 (50.50, 51.35) | 52.19 (51.89, 52.78) |  | 1.041 | 1.196 |
| collatz | 95.69 (95.61, 95.89) | 78.09 (77.90, 78.31) | 57.56 (57.49, 57.69) |  | 1.225 slow | 4.178 |
| fnv | 92.59 (92.37, 93.22) | 93.08 (92.86, 93.66) | 92.97 (92.93, 93.15) |  | 0.995 | 1.000 |
| isort | 79.55 (79.44, 79.98) | 50.18 (50.11, 50.33) | 70.66 (70.58, 71.03) |  | 1.585 slow | 3.143 |

Geometric mean of AIL / C: 1.150, from 1.742. Startup is unchanged, 67 to 72 microseconds against 209 to 217 for C.

Three programs are within 5% of C. Two are not, and the disassembly says why:

- `isort`: the inner loop is 11 instructions with three jumps a round against 7 with two in C. The source has the loop as a label with two tests and a `jmp` back, so the jump back is a third jump; it compares the place with the start of the table by loading that address every round (`lea` / `cmp`), where C keeps it in a register; and it moves the place through a second variable (`sub p 8 as q` ... `set q as p`), where C indexes with a scale.
- `collatz`: C tests the low bit, computes `3x+1` with one `lea`, and lets the halving that must follow an odd step run in the same round. AIL takes the two branches of the block as written, each ending in a jump, and the time of this loop moves by 8% with where its code happens to lie, since its one branch cannot be predicted.

Part of that is in how the examples are written and goes away when they are written with `while` and expressions; that is measured after the examples are rewritten. What would remain needs analysis across statements, which this compiler does not do: keeping an address that does not change in a loop in a register, and a scaled index.

## With the examples rewritten, 2026-11-20

The examples now use expressions, `while` and loads and stores with an address expression; `collatz` and `isort` have their loops as `while` blocks, `isort` leaving its inner one by a jump when the place is found.

| program | AIL | C | Rust | asm | AIL / C |
|---|---:|---:|---:|---:|---:|
| compute | 132.38 (132.10, 132.46) | 132.53 (132.37, 132.60) | 132.67 (132.58, 132.94) | 132.43 (132.20, 133.38) | 0.999 |
| sieve | 52.35 (52.19, 53.17) | 50.74 (50.62, 50.94) | 52.42 (52.08, 52.59) |  | 1.032 |
| collatz | 88.24 (88.15, 88.31) | 78.44 (78.35, 78.72) | 57.96 (57.88, 58.04) |  | 1.125 slow |
| fnv | 92.99 (92.80, 93.16) | 93.48 (93.08, 93.85) | 93.62 (93.40, 94.23) |  | 0.995 |
| isort | 53.44 (52.41, 54.18) | 50.39 (50.31, 50.54) | 70.88 (70.81, 71.06) |  | 1.061 |

Geometric mean of AIL / C: 1.041, within the 5% the redesign set as its first threshold. `isort` went from 1.59 to 1.06 by being written as it would be in any language with a loop statement: the test of the loop at its end, and `load p-8 as v` instead of a second variable for the place; it is a quarter faster than the Rust program, which checks its indexes. `collatz` is the one program more than 10% behind C, for the reason given above: C folds the halving after an odd step into the same round and computes `3x+1` with one `lea`, and AIL compiles the two branches as written.

## Against the criteria of the redesign, 2026-11-20

`REDESIGN.md` set four criteria. This is where each stands, measured with the final compiler, the one written with expressions and built from seed 2.

**Source cost: met.** The corpus is fixed at ten programs, the six that existed and four added for arithmetic, control flow and memory access. AIL was 970 tokens, Rust 1191, C 1118, with AIL below C in all ten programs and above Rust in two, `count` (118 against 102) and `fnv` (141 against 126). Since the examples are indented, later the same day, AIL is 990: still below Rust, below C in nine and two tokens above it in `collatz` (121 against 119), with `fnv` at 144; [tokens.md](tokens.md) lists every program.

**CPU performance: the threshold is met, parity is not.** Geometric mean of elapsed time against `gcc -O3` over the five CPU-bound programs: 1.039, inside the 5% set as the first threshold.

| program | AIL | C | Rust | asm | AIL / C |
|---|---:|---:|---:|---:|---:|
| compute | 131.56 (131.19, 131.91) | 131.68 (131.38, 132.19) | 131.81 (131.73, 132.35) | 131.51 (131.23, 131.62) | 0.999 |
| sieve | 52.34 (52.08, 52.75) | 50.62 (50.51, 50.98) | 52.41 (52.09, 53.05) |  | 1.034 |
| collatz | 87.70 (87.59, 87.91) | 77.90 (77.71, 78.06) | 57.55 (57.47, 57.93) |  | 1.126 slow |
| fnv | 92.24 (92.14, 92.64) | 92.48 (92.42, 92.79) | 92.79 (92.49, 92.95) |  | 0.997 |
| isort | 52.20 (51.54, 52.90) | 50.02 (50.01, 50.17) | 70.38 (70.31, 70.48) |  | 1.043 |

One program is more than 10% slower than C and is reported as the criterion asks: `collatz`, 12.6%. Every figure is a median of 15 runs with its quartiles, on runtime input, after each executable gave the result of the C program; the compilers, flags and machine are those at the top of this page. Startup is reported apart: 68 to 73 microseconds for AIL, as for assembly, against 210 to 216 for C and 293 to 304 for Rust.

**Authoring effort: met.** `count` writes each digit in one line (`storeb out+1 48+n/100%10`) with no variable for a digit or an address, and `compute` is a `while` around one assignment, with no label. Beyond the representative routines the criterion asks for, the whole compiler was rewritten by `tools/infix.py` (now `tools/infix.aw`): 21% fewer tokens, 725 intermediate variables gone, 12 loops as `while`.

**Bootstrap: met.** `stage0/ailc.s` is removed. A clean worktree with an empty build directory and no local seed fetches seed 2 by the checksum in `seed/MANIFEST`, builds the compiler in three generations, passes `make test` (2065 checks) and has `build/ailc` and `build/ailc2` as one file.

Where the work departed from the document:

- **Expressions have no spaces.** The document sketched `x = (x ^ (x >> 13)) * 1664525 + 1013904223` with a comma between arguments and left the boundaries to be settled by counting. The count chose `x=(x^x>>13)*1664525+1013904223`, with spaces separating arguments as before: 975 tokens for the corpus against 1104 for the sketched form.
- **Three generations, not two.** The document builds the sources with the seed and then with the result, and compares those two. Once the compiler writes other code than its seed did, those two differ in bytes without differing in behaviour, so the comparison is between the second and a third build.
- **No intermediate representation across statements.** Expressions are parsed into a list of nodes and lowered together, each statement on its own, as the document says to start. What remains behind C would need more: an address that does not change in a loop kept in a register, a scaled index, and the folding of one branch into another that C does in `collatz`.
- **A step was added**, loads and stores with the address as an operand, when the measurements after instruction selection showed where `isort` and `sieve` lost.
- **The old forms were not kept as copies of the examples.** The statements remain in the language and compile to the same code as the expressions that say the same, which `tools/randvars.aw` checks byte for byte; about 900 tests are still written with them.
- **The seed is a release of a private repository**, so fetching it needs `gh` and a login, or a local copy; `curl` alone works once the repository is public.

The comparison of these token counts, taken with an OpenAI encoding, with Claude's tokenizer was not done then; it was on 2026-11-21, and the order of the languages holds, with AIL further ahead ([tokens.md](tokens.md#claudes-tokenizers-measured-on-2026-11-21)).

## The measuring tool in AIL, 2026-11-20

The figures above were taken by `tools/perf.py`, a Python script. The tool is now `tools/perf.aw`, which checks, times and reports as the script did: the same programs, inputs and expected results, the same rounds, the median and quartiles of the same samples, the same tables. Two things differ, and one of them touches the figures.

- **How a child is started.** The script called `posix_spawn` from the Python interpreter; the tool calls `procstart` of `lib/std/proc.aw`, which is `fork` and then `dup2` and `execve` in the child. The time of a run includes that start, and for the startup programs it is most of what is measured. Both tools were run one after the other, twice, on the same machine and CPU as above (median of 500 rounds, microseconds):

| program | AIL, script | AIL, tool | C, script | C, tool |
|---|---:|---:|---:|---:|
| hello | 70.84, 72.69 | 71.78, 69.30 | 218.92, 227.86 | 223.00, 214.42 |
| echo | 75.25, 76.79 | 74.16, 71.81 | 229.78, 231.85 | 223.72, 215.26 |
| count | 76.64, 80.91 | 71.99, 70.31 | 226.71, 242.46 | 218.25, 211.25 |
| fact | 72.75, 74.69 | 68.95, 67.40 | 229.67, 231.28 | 211.91, 206.97 |
| loop | 78.93, 78.21 | 71.80, 70.44 | 235.43, 231.46 | 214.94, 209.74 |

  Taking the two runs of each together, the tool's figures are lower than the script's: by 1 to 8 microseconds for AIL and by 5 to 21 for C, between 2 and 10 percent. For AIL that is within the quartiles of either tool, which lie 4 to 9 microseconds from the median; for C it is beyond them in three of the five programs. A `fork` of a process of a few pages costs less than a `posix_spawn` out of an interpreter, and a parent that runs no interpreter between two children leaves them warmer caches, which a program that loads a C library uses more of. The ratio between the languages is what it was, an AIL program starting in about a third of the time of the C one with either tool (`hello` 0.32 and 0.32, `count` 0.34 and 0.33). So startup figures taken by the tool are not to be mixed with the older tables above, only compared within a table; the CPU-bound figures, in milliseconds, do not see the difference. The script's start could have been matched with `clone` and `CLONE_VFORK`, but not its interpreter, so the plain `fork` stays and the figures are marked by date.
- **The order of a round** is shuffled with splitmix64 (`tools/lib/rng.aw`) from the seed 77, where the script used Python's generator with that seed: other orders, drawn the same way.

The geometric mean is the n-th root by Newton's steps in doubles, where Python used `pow`; on 20000 random products of up to ten ratios the two print the same three digits. A run of every program with the tool gave 1.000, 1.029, 1.126, 0.999 and 1.043 for the five CPU-bound programs and a geometric mean of 1.038.

## Algorithms, 2026-11-20

*The kernel times of this section were taken with the clock as a system call in AIL, which adds 78 ns a run to AIL alone: rows of a thousand runs or more overstate AIL / C ([the clock of the harness](#the-clock-of-the-harness-2026-11-21)).*

The five CPU-bound programs above are loops over a few variables. The six algorithm programs ask what the compiler makes of code that calls functions and walks arrays: three sorts, a binary search, a hash table and a graph traversal, each the same algorithm with the same arrays in AIL, C and Rust.

A program reads five words from stdin: a seed, a count, a shape, a number of runs and a mode (`bench/algo/harness.aw`, with `harness.h` and `harness.rs` for the other two languages). It makes its input from the seed with splitmix64, so the three languages work on the same words, and then runs its kernel that many times, each time on a fresh copy of the input. The copy, the making of the input, the clearing of a table and the checksum are outside the timed region: the monotonic clock is read just before and just after each call of the kernel (`clock_gettime` in AIL and C, `Instant` in Rust), and the sum over the runs is written behind a checksum of the last result. `perf` sends that output to a file, reads it after every run, requires the checksum of C in it, and reports the time of the kernel apart from the elapsed time of the process, which also has the start of the process and the setup in it.

The inputs are rows of a table in `tools/perf.aw`: a thousand elements with enough runs for some tens of milliseconds, and a million with one to four runs, in up to four shapes. `random` is words of 64 bits; `sorted` and `reversed` rise and fall by steps below 65536; `few` is values below 16 for a sort, steps below 2 for `bsearch`, 128 distinct keys for `hashtab` and at most one neighbour a vertex for `graph`, where `random` is up to 15. A small array lies in the first cache and shows the instructions; a large one shows the memory as well.

Before anything is timed the whole result, not its checksum, must be the same bytes as that of C at the counts 0, 1 and 1000 in the shape of the row. That the results are right, and not only alike, is checked by `tests/algo.sh` in `make test`: the sorts against `sort(1)`, the others against awk programs, the empty and the one-element array among the counts. Since 2026-11-21 it asks that of the programs in C and Rust as well, where there is a `gcc` and a `rustc`.

The time of the kernels (same machine, CPU and compilers as above; the column `C qsort` is `bench/qsort.c`, the sort of the C library with a comparison function, on the same input):

| program, input, count x runs | AIL | C | Rust | C qsort | AIL / C |
|---|---:|---:|---:|---:|---:|
| quicksort, random, 1000 x 1000 | 24.66 (24.53, 25.47) | 13.65 (13.41, 14.07) | 20.63 (20.44, 20.78) | 33.19 (32.75, 33.41) | 1.807 slow |
| quicksort, sorted, 1000 x 1000 | 26.76 (26.50, 27.09) | 10.89 (10.85, 10.95) | 13.92 (13.89, 14.01) | 14.02 (13.96, 14.14) | 2.457 slow |
| quicksort, reversed, 1000 x 1000 | 22.93 (22.69, 23.14) | 9.46 (9.42, 9.51) | 12.27 (12.11, 12.34) | 15.40 (15.32, 15.45) | 2.425 slow |
| quicksort, few, 1000 x 1000 | 6.11 (6.07, 6.16) | 2.85 (2.83, 2.88) | 2.93 (2.89, 2.94) | 25.94 (25.56, 26.34) | 2.145 slow |
| quicksort, random, 1000000 x 1 | 84.08 (83.80, 84.37) | 66.06 (65.90, 66.52) | 72.07 (71.96, 72.36) | 94.63 (94.37, 95.57) | 1.273 slow |
| quicksort, sorted, 1000000 x 1 | 603.89 (598.82, 608.61) | 342.10 (338.97, 355.07) | 361.18 (356.02, 362.84) | 26.58 (26.37, 26.72) | 1.765 slow |
| quicksort, reversed, 1000000 x 1 | 427.82 (425.07, 438.50) | 240.25 (218.28, 254.84) | 237.76 (235.80, 248.70) | 28.63 (28.43, 28.88) | 1.781 slow |
| quicksort, few, 1000000 x 4 | 59.12 (58.88, 59.38) | 45.78 (45.46, 46.04) | 48.57 (48.36, 48.93) | 237.17 (236.64, 237.79) | 1.291 slow |
| mergesort, random, 1000 x 1000 | 29.95 (29.69, 30.32) | 18.27 (17.91, 18.69) | 15.80 (15.69, 15.98) | 33.18 (32.66, 33.46) | 1.640 slow |
| mergesort, sorted, 1000 x 1000 | 14.32 (14.22, 14.44) | 5.25 (5.23, 5.29) | 5.14 (5.11, 5.19) | 14.04 (14.03, 14.07) | 2.726 slow |
| mergesort, reversed, 1000 x 1000 | 14.92 (14.85, 15.01) | 5.14 (5.12, 5.14) | 4.64 (4.63, 4.67) | 14.53 (14.43, 14.59) | 2.904 slow |
| mergesort, few, 1000 x 1000 | 17.55 (17.49, 17.69) | 8.80 (8.75, 8.91) | 8.52 (8.48, 8.53) | 24.87 (24.77, 25.31) | 1.994 slow |
| mergesort, random, 1000000 x 1 | 100.33 (100.08, 101.05) | 67.39 (66.88, 67.84) | 66.61 (66.28, 66.82) | 94.53 (94.34, 94.64) | 1.489 slow |
| mergesort, sorted, 1000000 x 1 | 30.52 (29.94, 31.10) | 10.98 (10.76, 11.08) | 11.40 (11.10, 11.75) | 27.55 (27.21, 27.89) | 2.778 slow |
| mergesort, reversed, 1000000 x 1 | 31.20 (29.99, 32.06) | 10.92 (10.75, 11.20) | 10.87 (10.62, 11.08) | 29.90 (28.62, 30.15) | 2.858 slow |
| mergesort, few, 1000000 x 1 | 50.84 (50.14, 51.10) | 31.04 (30.54, 31.49) | 30.64 (30.40, 31.16) | 60.24 (60.06, 60.74) | 1.638 slow |
| heapsort, random, 1000 x 1000 | 26.81 (25.79, 27.26) | 18.06 (17.72, 18.63) | 16.53 (16.04, 16.79) | 33.78 (33.25, 34.64) | 1.485 slow |
| heapsort, sorted, 1000 x 1000 | 27.80 (26.89, 28.33) | 13.72 (13.46, 18.10) | 21.15 (20.97, 21.28) | 14.39 (14.30, 14.48) | 2.025 slow |
| heapsort, reversed, 1000 x 1000 | 20.02 (19.04, 20.98) | 11.60 (11.23, 11.71) | 19.51 (19.16, 19.70) | 14.78 (14.63, 15.09) | 1.726 slow |
| heapsort, few, 1000 x 1000 | 17.94 (17.46, 20.37) | 15.45 (15.20, 17.33) | 16.44 (16.35, 16.75) | 24.72 (24.55, 24.94) | 1.161 slow |
| heapsort, random, 1000000 x 1 | 105.62 (105.13, 106.08) | 80.14 (79.61, 80.86) | 71.33 (70.70, 71.55) | 94.67 (94.44, 94.96) | 1.318 slow |
| heapsort, sorted, 1000000 x 1 | 47.75 (47.63, 48.04) | 35.92 (35.89, 36.18) | 31.08 (30.96, 31.28) | 26.31 (26.20, 26.44) | 1.330 slow |
| heapsort, reversed, 1000000 x 1 | 50.74 (50.59, 50.80) | 35.37 (35.09, 35.49) | 30.80 (30.67, 31.14) | 28.65 (28.52, 28.88) | 1.434 slow |
| heapsort, few, 1000000 x 1 | 53.76 (53.45, 54.01) | 38.92 (38.75, 39.10) | 34.54 (34.34, 34.79) | 60.64 (60.46, 61.10) | 1.381 slow |
| bsearch, random, 1000 x 2000 | 57.77 (57.19, 59.35) | 54.37 (53.98, 55.05) | 41.91 (41.40, 42.36) |  | 1.063 |
| bsearch, few, 1000 x 2000 | 57.32 (57.03, 58.56) | 52.88 (52.30, 53.65) | 39.35 (39.08, 39.81) |  | 1.084 |
| bsearch, random, 1000000 x 1 | 132.63 (130.53, 133.97) | 121.01 (119.84, 121.33) | 125.85 (125.19, 127.86) |  | 1.096 |
| bsearch, few, 1000000 x 1 | 131.14 (130.12, 131.83) | 119.44 (118.84, 120.24) | 124.79 (123.83, 126.37) |  | 1.098 |
| hashtab insert, random, 1000 x 5000 | 11.65 (11.61, 11.78) | 5.50 (5.46, 5.60) | 5.81 (5.70, 6.06) |  | 2.117 slow |
| hashtab hit, random, 1000 x 5000 | 13.02 (12.98, 13.05) | 8.61 (8.53, 8.67) | 7.17 (7.13, 7.20) |  | 1.514 slow |
| hashtab miss, random, 1000 x 5000 | 14.71 (14.66, 14.78) | 9.49 (9.40, 9.61) | 10.39 (10.30, 10.48) |  | 1.550 slow |
| hashtab insert, few, 1000 x 5000 | 9.44 (9.42, 9.52) | 4.19 (4.04, 4.33) | 4.69 (4.62, 4.96) |  | 2.253 slow |
| hashtab hit, few, 1000 x 5000 | 11.49 (11.38, 11.53) | 3.38 (3.36, 3.40) | 5.04 (5.03, 5.07) |  | 3.403 slow |
| hashtab miss, few, 1000 x 5000 | 13.20 (13.18, 13.37) | 3.47 (3.44, 3.52) | 5.20 (5.17, 5.23) |  | 3.805 slow |
| hashtab insert, random, 1000000 x 2 | 29.82 (29.47, 30.74) | 21.06 (20.54, 21.98) | 21.40 (21.20, 22.08) |  | 1.416 slow |
| hashtab hit, random, 1000000 x 2 | 34.69 (34.33, 36.15) | 23.65 (23.39, 24.33) | 25.33 (25.07, 26.03) |  | 1.467 slow |
| hashtab miss, random, 1000000 x 2 | 62.36 (61.09, 65.37) | 41.81 (41.27, 42.35) | 41.15 (40.76, 42.08) |  | 1.492 slow |
| hashtab insert, few, 1000000 x 4 | 7.32 (7.30, 7.37) | 3.46 (3.39, 3.47) | 4.13 (4.05, 4.18) |  | 2.115 slow |
| hashtab hit, few, 1000000 x 4 | 10.57 (10.51, 10.62) | 4.58 (4.49, 4.70) | 6.37 (6.25, 6.41) |  | 2.309 slow |
| hashtab miss, few, 1000000 x 4 | 48.45 (47.97, 49.40) | 26.93 (26.68, 27.16) | 30.18 (29.61, 30.61) |  | 1.799 slow |
| graph bfs, random, 1000 x 2000 | 21.76 (21.43, 22.16) | 10.93 (10.72, 11.06) | 9.80 (9.78, 10.05) |  | 1.991 slow |
| graph dfs, random, 1000 x 2000 | 45.77 (45.17, 46.08) | 15.32 (14.99, 15.87) | 18.74 (18.62, 19.21) |  | 2.988 slow |
| graph bfs, few, 1000 x 5000 | 14.55 (14.52, 14.56) | 8.05 (8.01, 8.10) | 8.80 (8.78, 8.82) |  | 1.807 slow |
| graph dfs, few, 1000 x 5000 | 17.79 (17.72, 17.90) | 7.28 (7.26, 7.33) | 14.03 (14.01, 14.22) |  | 2.443 slow |
| graph bfs, random, 1000000 x 1 | 90.59 (89.93, 91.37) | 57.51 (57.01, 58.56) | 74.07 (72.84, 77.64) |  | 1.575 slow |
| graph dfs, random, 1000000 x 1 | 149.44 (147.44, 156.63) | 128.77 (127.87, 135.46) | 135.24 (132.86, 139.69) |  | 1.160 slow |
| graph bfs, few, 1000000 x 2 | 31.65 (30.66, 32.00) | 26.30 (25.60, 27.02) | 27.81 (27.65, 27.98) |  | 1.204 slow |
| graph dfs, few, 1000000 x 2 | 33.30 (33.12, 34.56) | 26.43 (26.15, 26.93) | 30.46 (30.34, 30.58) |  | 1.260 slow |

Geometric mean of AIL / C over 48 kernel times: 1.753

The elapsed time of the same processes:

| program, input, count x runs | AIL | C | Rust | C qsort | AIL / C |
|---|---:|---:|---:|---:|---:|
| quicksort, random, 1000 x 1000 | 25.36 (25.20, 26.15) | 14.10 (13.83, 14.51) | 21.13 (20.99, 21.30) | 33.59 (33.15, 33.82) | 1.798 slow |
| quicksort, sorted, 1000 x 1000 | 27.41 (27.15, 27.80) | 11.30 (11.26, 11.43) | 14.42 (14.36, 14.65) | 14.41 (14.38, 14.60) | 2.425 slow |
| quicksort, reversed, 1000 x 1000 | 23.62 (23.39, 23.84) | 9.90 (9.85, 10.00) | 12.75 (12.60, 12.89) | 15.83 (15.71, 15.88) | 2.385 slow |
| quicksort, few, 1000 x 1000 | 6.81 (6.77, 6.87) | 3.27 (3.24, 3.31) | 3.41 (3.38, 3.46) | 26.33 (25.98, 26.75) | 2.082 slow |
| quicksort, random, 1000000 x 1 | 91.21 (91.06, 91.69) | 72.21 (71.94, 72.72) | 78.98 (78.88, 79.42) | 100.99 (100.59, 101.51) | 1.263 slow |
| quicksort, sorted, 1000000 x 1 | 611.83 (606.55, 616.39) | 348.36 (345.54, 361.69) | 368.20 (362.39, 369.98) | 33.01 (32.69, 33.23) | 1.756 slow |
| quicksort, reversed, 1000000 x 1 | 435.77 (433.10, 446.62) | 246.93 (224.36, 261.02) | 244.20 (242.34, 255.91) | 35.10 (34.97, 35.47) | 1.765 slow |
| quicksort, few, 1000000 x 4 | 67.74 (67.24, 68.71) | 52.82 (52.42, 53.36) | 56.81 (56.25, 57.05) | 245.07 (244.29, 245.72) | 1.283 slow |
| mergesort, random, 1000 x 1000 | 30.63 (30.37, 30.99) | 18.68 (18.35, 19.08) | 16.29 (16.18, 16.47) | 33.58 (33.07, 33.85) | 1.640 slow |
| mergesort, sorted, 1000 x 1000 | 14.99 (14.87, 15.09) | 5.64 (5.60, 5.70) | 5.60 (5.56, 5.66) | 14.43 (14.40, 14.46) | 2.658 slow |
| mergesort, reversed, 1000 x 1000 | 15.59 (15.49, 15.66) | 5.52 (5.50, 5.56) | 5.09 (5.08, 5.11) | 14.89 (14.81, 14.98) | 2.824 slow |
| mergesort, few, 1000 x 1000 | 18.20 (18.16, 18.35) | 9.21 (9.14, 9.35) | 9.00 (8.97, 9.05) | 25.27 (25.18, 25.69) | 1.975 slow |
| mergesort, random, 1000000 x 1 | 108.05 (107.44, 108.50) | 73.79 (73.30, 74.31) | 73.63 (73.30, 73.98) | 100.64 (100.18, 101.02) | 1.464 slow |
| mergesort, sorted, 1000000 x 1 | 38.36 (37.61, 38.97) | 17.64 (17.39, 18.00) | 18.41 (17.86, 18.98) | 34.03 (33.69, 34.44) | 2.175 slow |
| mergesort, reversed, 1000000 x 1 | 39.15 (37.64, 40.02) | 17.54 (17.31, 17.68) | 17.79 (17.39, 18.01) | 36.14 (34.96, 36.32) | 2.233 slow |
| mergesort, few, 1000000 x 1 | 58.24 (57.44, 58.73) | 37.53 (37.00, 38.11) | 37.47 (37.27, 38.51) | 66.40 (65.74, 66.59) | 1.552 slow |
| heapsort, random, 1000 x 1000 | 27.48 (26.46, 28.04) | 18.57 (18.17, 19.06) | 17.05 (16.55, 17.36) | 34.21 (33.72, 35.06) | 1.480 slow |
| heapsort, sorted, 1000 x 1000 | 28.46 (27.58, 29.03) | 14.16 (13.88, 18.54) | 21.71 (21.50, 21.83) | 14.83 (14.73, 14.91) | 2.010 slow |
| heapsort, reversed, 1000 x 1000 | 20.70 (19.70, 21.64) | 12.00 (11.73, 12.18) | 20.13 (19.66, 20.25) | 15.21 (15.05, 15.50) | 1.724 slow |
| heapsort, few, 1000 x 1000 | 18.63 (18.12, 21.07) | 15.85 (15.61, 17.73) | 16.95 (16.88, 17.35) | 25.12 (24.96, 25.37) | 1.175 slow |
| heapsort, random, 1000000 x 1 | 112.73 (112.33, 113.53) | 86.33 (85.71, 87.05) | 78.31 (77.60, 78.63) | 101.13 (100.64, 101.26) | 1.306 slow |
| heapsort, sorted, 1000000 x 1 | 54.73 (54.48, 55.02) | 41.89 (41.76, 42.22) | 37.35 (36.86, 37.75) | 32.30 (31.99, 32.60) | 1.306 slow |
| heapsort, reversed, 1000000 x 1 | 57.79 (57.54, 58.01) | 41.15 (40.88, 41.62) | 37.26 (37.04, 37.52) | 34.64 (34.24, 34.76) | 1.404 slow |
| heapsort, few, 1000000 x 1 | 61.07 (60.39, 61.34) | 45.05 (44.58, 45.35) | 41.44 (41.07, 41.58) | 66.55 (66.13, 67.69) | 1.356 slow |
| bsearch, random, 1000 x 2000 | 58.15 (57.54, 59.78) | 54.75 (54.39, 55.49) | 42.50 (41.92, 42.89) |  | 1.062 |
| bsearch, few, 1000 x 2000 | 57.66 (57.40, 58.97) | 53.24 (52.72, 54.02) | 39.97 (39.62, 40.41) |  | 1.083 |
| bsearch, random, 1000000 x 1 | 142.05 (139.81, 143.39) | 129.53 (128.66, 129.89) | 134.41 (133.76, 136.80) |  | 1.097 |
| bsearch, few, 1000000 x 1 | 140.49 (139.45, 141.30) | 128.00 (127.29, 128.99) | 133.14 (132.33, 134.90) |  | 1.098 |
| hashtab, random, 1000 x 5000 | 62.36 (61.10, 65.30) | 24.79 (24.65, 25.03) | 24.83 (24.46, 24.97) |  | 2.516 slow |
| hashtab, few, 1000 x 5000 | 57.34 (56.26, 66.13) | 12.09 (11.99, 12.33) | 16.26 (16.10, 16.47) |  | 4.742 slow |
| hashtab, random, 1000000 x 2 | 155.35 (154.03, 160.21) | 105.87 (105.00, 108.50) | 109.16 (107.96, 109.98) |  | 1.467 slow |
| hashtab, few, 1000000 x 4 | 107.51 (107.06, 109.32) | 56.08 (55.88, 57.44) | 62.69 (61.88, 63.32) |  | 1.917 slow |
| graph, random, 1000 x 2000 | 70.06 (69.42, 70.59) | 26.80 (26.19, 27.42) | 29.13 (29.02, 29.87) |  | 2.614 slow |
| graph, few, 1000 x 5000 | 38.54 (38.48, 38.66) | 15.89 (15.83, 15.97) | 23.46 (23.41, 23.64) |  | 2.425 slow |
| graph, random, 1000000 x 1 | 285.61 (281.74, 290.74) | 223.39 (222.66, 234.80) | 272.08 (268.99, 276.05) |  | 1.279 slow |
| graph, few, 1000000 x 2 | 78.99 (78.20, 81.58) | 63.56 (63.09, 65.50) | 73.00 (72.81, 73.51) |  | 1.243 slow |

Geometric mean of AIL / C over 36 inputs: 1.714

What the figures say:

- AIL takes 1.75 times as long as C in the geometric mean of the kernels, where the five CPU-bound programs take 1.04. The goal of the redesign was set and met on loops; these programs are where it is not met.
- `bsearch` is within 10% of C at both sizes. Its inner loop is a load, a comparison and a branch that cannot be predicted.
- The small inputs are mostly behind by more than the large ones: the sorts on random input take 1.5 to 1.8 times as long as C at a thousand elements and 1.3 to 1.5 times at a million, the two traversals 2.0 and 3.0 times against 1.6 and 1.2. At a million a part of the time is the memory, which costs every language the same; at a thousand everything is in the first cache and the instructions are all there is.
- Where C is far ahead the kernel calls a small function in its inner loop, which `gcc -O3` puts in line and the AIL compiler does not: `place` in `hashtab` (1.5 to 3.8 times at a thousand keys), `sift` in `heapsort`. That is the likely cause, not a measured one; the merge loop of `mergesort`, which calls nothing, is 1.6 to 2.9 times behind as well, so the code of a loop with five variables in memory operands and two branches is part of it.
- `quicksort` on a million words in order takes five to seven times as long as on random ones, in the three languages alike. The likely cause is the pass that keeps equal words in the middle: it brings the words from the end to the front one by one, which leaves the larger side of an ordered input out of order with its smallest word last, where the median of three picks a poor pivot. The `qsort` of the C library, a merge sort, is 13 times faster there than the C quicksort, and 5 times slower on few values, where the three-way pass has little to do.
- Against the library's `qsort`, which a C program gets without writing a sort, the AIL kernels are faster on random input at both sizes except `mergesort` and `heapsort` at a million, and slower on input in order.

The source of each program, in tokens (`tools/tokens.sh`, the kernel and the two or three lines that hand it to the harness, without comments):

| program | AIL | C | Rust |
|---|---:|---:|---:|
| quicksort | 264 | 297 | 324 |
| mergesort | 309 | 298 | 340 |
| heapsort | 211 | 237 | 271 |
| bsearch | 129 | 156 | 163 |
| hashtab | 238 | 280 | 280 |
| graph | 373 | 345 | 405 |
| total | 1524 | 1613 | 1783 |
| relative to AIL | 1.00 | 1.06 | 1.17 |

## Scaled indexes, 2026-11-20

The first step of the follow-up in the backlog. A load or store whose address ends in a variable times 2, 4 or 8, as `load p+i*8` and `load a+c*8+8`, was an `imul`, an `add` and a `mov`; it is one `mov` now, with the variable as a scaled index in the memory operand (`[rbx+rbp*8+8]`), and a number and an index are both taken into the operand where one of them was before. The same full run of `perf` before and after, AIL against C in the geometric mean of the kernel times of each program:

| program | before | after |
|---|---:|---:|
| quicksort | 1.818 | 1.856 |
| mergesort | 2.175 | 2.068 |
| heapsort | 1.462 | 1.227 |
| bsearch | 1.085 | 0.882 |
| hashtab | 1.993 | 1.883 |
| graph | 1.709 | 1.695 |
| all 48 kernel times | 1.753 | 1.639 |

`heapsort` and `bsearch` index an array in their inner loops and gain most: the kernel of `heapsort` takes 14 to 31% less time, and `bsearch` on a thousand elements 30% less, which puts it ahead of C there (0.74 and 0.76), while on a million, where the memory decides, it gains 6%. `hashtab` gains 4 to 9% and `mergesort` up to 17% on input in order. `quicksort` walks pointers and has one such address outside its loops, so its times moved by less than 2% either way; its ratio differs by what the C times of the two runs differ. `graph` gained up to 7% at a thousand vertices and lost 7% in one row at a million (`bfs`, few neighbours), within what that row varies between runs of the earlier table. The five CPU-bound programs stay at 1.04 (1.038 and 1.039).

## Registers by weight, 2026-11-20

The second step. A function has six registers for its variables, and they went to the first six that were defined, which are the parameters: in `lookup` of `hashtab` the table, the mask and the three arrays had registers, each read once a round, and the key, its place, the value and the word just loaded lived on the stack. The registers now go to the variables that are mentioned most, a mention in a loop counting 8 times for each loop around it ([how](https://github.com/CristianosLeite/ail/blob/main/docs/compiler.md)). In the executable of `hashtab` the instructions that name `rsp` went from 228 to 143, and its code from 4613 to 3941 bytes. AIL against C in the geometric mean of the kernel times of each program, before the follow-up, with scaled indexes, and with both:

| program | before | scaled indexes | and registers by weight |
|---|---:|---:|---:|
| quicksort | 1.818 | 1.856 | 1.214 |
| mergesort | 2.175 | 2.068 | 1.200 |
| heapsort | 1.462 | 1.227 | 1.127 |
| bsearch | 1.085 | 0.882 | 0.977 |
| hashtab | 1.993 | 1.883 | 1.835 |
| graph | 1.709 | 1.695 | 1.341 |
| all 48 kernel times | 1.753 | 1.639 | 1.325 |

- `quicksort` and `mergesort`, each one function with ten or more variables, take 13 to 56% less time: on input in order about half, and `quicksort` on a million such words is level with C (1.01 and 1.02).
- `graph` takes up to 53% less (`dfs` at a thousand vertices) and `heapsort` up to 27% less at a thousand elements; at a million `heapsort` is as it was, within 2%.
- `bsearch` at a thousand elements takes 22 and 24% more time than with scaled indexes alone, and is still ahead of C (0.92). Its functions have six and seven variables, so at most one of them changed its place; why that costs so much in a loop of a load, a comparison and a branch was not looked into. At a million it is unchanged.
- `hashtab` gains up to 12% at a thousand keys and loses 4 to 5% in the three kernels on a million random keys, where the time is the memory. It stays the furthest from C, since its loops are calls of `place`.
- The five CPU-bound programs are at top level, where nothing changed: 1.037.

**Inlining, evaluated.** `hashtab` was written a second time with `place` in the two loops by hand and timed the same way, 15 rounds, against the form with the call (kernel times in milliseconds, both built by the compiler with registers by weight):

| kernel, input | with the call | written in line | |
|---|---:|---:|---:|
| insert, random, 1000 x 5000 | 10.34 | 8.57 | -17% |
| hit, random, 1000 x 5000 | 10.98 | 8.48 | -23% |
| miss, random, 1000 x 5000 | 13.36 | 11.01 | -18% |
| insert, few, 1000 x 5000 | 8.84 | 6.49 | -27% |
| hit, few, 1000 x 5000 | 9.31 | 6.52 | -30% |
| miss, few, 1000 x 5000 | 12.03 | 6.42 | -47% |
| insert, random, 1000000 x 2 | 28.91 | 29.82 | +3% |
| hit, random, 1000000 x 2 | 33.57 | 31.98 | -5% |
| miss, random, 1000000 x 2 | 60.37 | 57.10 | -5% |
| insert, few, 1000000 x 4 | 6.88 | 5.16 | -25% |
| hit, few, 1000000 x 4 | 8.89 | 7.15 | -20% |
| miss, few, 1000000 x 4 | 47.15 | 38.60 | -18% |

The code is 102 bytes longer (4035 against 3933) and names `rsp` 151 times against 143. With the registers in order of definition the same rewriting had made the lookups that miss 30% slower, because the loop then had more variables on the stack than the call had cost; with the registers by weight it is faster in 11 of the 12 times and 3% slower in one, within what that row varies. Against C the inlined form is at 1.46 in the geometric mean of the twelve, the form with the call at 1.84. So inlining is worth having in the compiler now and was not before; it is a backlog item of its own, since a function is compiled from its source in two passes with no tree to copy.

## Two comparisons in a while, 2026-11-20

The third step. `while (i<p)&(j<q)` computed both comparisons as 1 or 0 and tested their `and`: two `cmp`, two `setcc`, two `movzx`, an `and` and a jump in every round. Two layouts with a jump for each comparison were tried by writing the loop of `mergesort` with labels and jumps, which gives exactly the code a lowering would, and timing them like any other form, twice each (kernel times, percent against the loop as it was compiled, mean of the two runs):

| input | both tests at the head, one jump back | tests before the loop and at its end |
|---|---:|---:|
| random, 1000 x 1000 | -2% | -3% |
| sorted, 1000 x 1000 | -12% | -11% |
| reversed, 1000 x 1000 | -1% | -17% |
| few, 1000 x 1000 | -1% | -10% |
| random, 1000000 x 1 | +1% | 0% |
| sorted, 1000000 x 1 | -18% | -17% |
| reversed, 1000000 x 1 | -9% | -18% |
| few, 1000000 x 1 | -6% | -8% |

The second is never slower and is what a `while` already is for one comparison, a test before the loop and one at its end, so the compiler now writes it: the first comparison jumps out when it fails, the second jumps back while it holds. An earlier trial of split branches, with the registers still in order of definition, had been up to 18% slower on small random input; with the loop's variables in registers that loss is gone. Only a `while` on exactly two comparisons joined by `&`, without a division, is compiled so: there the two forms cannot be told apart, since a comparison neither traps nor assigns. A full run, AIL against C in the geometric mean of the kernel times:

| program | registers by weight | and two jumps |
|---|---:|---:|
| mergesort | 1.200 | 1.056 |
| hashtab | 1.835 | 1.614 |
| all 48 kernel times | 1.325 | 1.257 |

`mergesort` takes 2 to 17% less time in its eight rows and is within 6% of C in the mean, behind by 22% only on few values at a thousand elements. `hashtab`, whose `place` has the loop `while (x!=0)&(x!=k)`, takes 4 to 22% less in its twelve. The other four programs have no such loop and are where they were, as are the five CPU-bound programs (1.036). Since the first measurement the 48 kernel times went from 1.753 times those of C to 1.257.

## Calls compiled in place, 2026-11-20

The fourth step. A `call` in a function, of a short function that stands before it, is now the body of that function ([how](https://github.com/CristianosLeite/ail/blob/main/docs/compiler.md#functions)): in `hashtab` the two loops hold the lines of `place`, in `bsearch` the loop of `search` holds `lower`. `sift` of `heapsort` is 165 bytes of code, over the limit of 160, and stays a call.

**Where the code lies moves the time.** The first run after the change had `heapsort` at a thousand elements 15 to 34% slower, though its two functions were the same bytes as before: only the harness in front of them had become longer. A line of `data` before the first function, which moves all code behind it, showed it. `heapsort` on a thousand random words, the unchanged compiler, kernel time against the bytes the code was moved by:

| moved by | 0 | 6 | 8 | 12 | 16 | 20 | 24 | 28 | 32 | 36 | 38 | 64 |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| ms | 20.4 | 18.6 | 18.0 | 23.5 | 23.8 | 22.9 | 23.1 | 21.7 | 20.7 | 15.5 | 16.0 | 20.5 |

The time repeats every 32 bytes and lies between 15.5 and 23.8 ms. So at a thousand elements a difference of a fifth between two builds may be nothing but the place of a loop among the 32-byte lines of the processor, and a comparison of two compilers must not rest on one place. `bsearch` at a thousand elements moves between 38 and 69 ms the same way; the rows of a million elements, where the time is the memory, move by 2 to 5%. The 22 to 24% that `bsearch` lost with [the registers by weight](#registers-by-weight-2026-11-20) are of this kind.

**The comparison, over eight places.** Each program was therefore built eight times by each compiler, with 1, 5, 9, ... 29 bytes of `data` before its first function, every executable run five times in turn on one CPU, and the median of each taken. Kernel times in milliseconds, the least, the median and the greatest of the eight places, with the call and with the body in place; the last column compares the medians:

| kernel, input | with the call | in place | |
|---|---:|---:|---:|
| `hashtab` insert, random, 1000 x 5000 | 9.20, 9.32, 9.79 | 6.19, 6.58, 6.97 | -29% |
| `hashtab` hit, random, 1000 x 5000 | 9.54, 9.69, 10.07 | 7.63, 7.78, 7.90 | -20% |
| `hashtab` miss, random, 1000 x 5000 | 11.40, 13.47, 14.95 | 9.43, 10.09, 12.28 | -25% |
| `hashtab` insert, few, 1000 x 5000 | 8.33, 8.44, 8.57 | 5.29, 5.40, 5.64 | -36% |
| `hashtab` hit, few, 1000 x 5000 | 8.24, 8.27, 8.81 | 6.10, 6.20, 6.62 | -25% |
| `hashtab` miss, few, 1000 x 5000 | 10.26, 10.53, 11.05 | 7.13, 7.68, 8.02 | -27% |
| `hashtab` insert, random, 1000000 x 2 | 26.06, 26.16, 27.24 | 22.43, 23.02, 23.40 | -12% |
| `hashtab` hit, random, 1000000 x 2 | 30.01, 30.55, 31.37 | 28.14, 28.43, 29.09 | -7% |
| `hashtab` miss, random, 1000000 x 2 | 47.44, 48.25, 49.21 | 44.88, 45.88, 46.55 | -5% |
| `hashtab` insert, few, 1000000 x 4 | 6.06, 6.41, 7.06 | 4.31, 4.98, 5.28 | -22% |
| `hashtab` hit, few, 1000000 x 4 | 8.34, 8.62, 9.31 | 6.86, 7.51, 8.40 | -13% |
| `hashtab` miss, few, 1000000 x 4 | 38.53, 40.68, 41.77 | 34.70, 36.52, 37.43 | -10% |
| `bsearch`, random, 1000 x 2000 | 45.11, 47.69, 57.46 | 38.17, 48.70, 68.92 | +2% |
| `bsearch`, few, 1000 x 2000 | 42.51, 47.74, 55.24 | 35.77, 46.75, 70.17 | -2% |
| `bsearch`, random, 1000000 x 1 | 122.11, 124.12, 126.09 | 122.94, 124.88, 127.53 | +1% |
| `bsearch`, few, 1000000 x 1 | 121.21, 121.79, 125.29 | 121.90, 124.74, 129.27 | +2% |

- `hashtab` takes 5 to 36% less time in all twelve, and in ten of them the slowest place of the new code is faster than the fastest of the old; in the misses on a thousand random keys and the hits on a million keys of few values the ranges meet. A round of its loops was a call, six pushes and pops and a return around a probe of one or two loads.
- `bsearch` is the same within what its place does. A call of `lower` runs ten rounds at a thousand elements and twenty at a million, so the call was a small part.
- `heapsort` with the limit raised to 200 bytes, so that `sift` is in place twice: 21.8 against 21.1 ms at a thousand random words (medians; the eight places span 16.3 to 25.4 and 18.2 to 23.0), and 98.5 against 92.3 ms at a million, 7% slower at every place. A call of `sift` runs a loop of up to twenty rounds, and in `heapsort` its seven variables come to those of the caller: eight are then in use in the loop for six registers, where `sift` alone has seven. The limit of 160 bytes is what separates the three functions measured, no more than that.

A parameter that the body only reads is the caller's variable itself, so `insert` with `place` in it is, instruction for instruction, the loop that was [written by hand](#registers-by-weight-2026-11-20) before. The executables of the six programs grew by 850 to 1117 bytes each (`hashtab` from 4087 to 5204), most of it the short functions of the harness at their calls. The compiler, compiled by itself, is 630 bytes longer than without (48779 against 48149) and compiles its own source in 5.4 ms against 5.7.

The full run, at one place each, AIL against C in the geometric mean of the kernel times:

| program | two jumps | and calls in place |
|---|---:|---:|
| quicksort | 1.215 | 1.201 |
| mergesort | 1.056 | 1.067 |
| heapsort | 1.138 | 1.263 |
| bsearch | 0.973 | 0.863 |
| hashtab | 1.614 | 1.306 |
| graph | 1.335 | 1.340 |
| all 48 kernel times | 1.257 | 1.201 |

Of these only the row of `hashtab` is the change. `heapsort` and `bsearch` are the same kernels at another place, one slower by it and one faster, which is the reason for the eight places above. The five CPU-bound programs are at top level and unchanged (1.036).

## The probe of hashtab in words, 2026-11-20

A place of the table is two words, and the probe of `place` counted places: its address was `t+h*16`, a multiplication and an addition before each load, since an x86-64 operand scales by 8 at most. The index now counts words: it stays even, goes on by two and is cut with twice the mask of the places, which `insert` and `lookup` make once (`m+=m`), and the hash is shifted by 31 where it was by 32, which is the same place doubled. The address is `t+h*8`, part of the load. The hash, the order of the probe, the size of the table and what a key given twice does are as they were, and the programs in C and Rust are unchanged; the output is compared with theirs as before, and `tests/algo.sh` has two inputs whose lookups go round the end of the table, which it proves with a table of its own. The source costs 238 tokens where it cost 230.

Measured [over eight places](#calls-compiled-in-place-2026-11-20) like the step before, with the compiler that compiles calls in place. Medians in milliseconds, and against the old probe; the second column is the old probe with its step spelled `h+=1` and `h&=m`, the same instructions less two moves, as a measure of what a row does by itself:

| kernel, input | places | places, respelled | | words | |
|---|---:|---:|---:|---:|---:|
| insert, random, 1000 x 5000 | 6.53 | 6.76 | +4% | 6.43 | -2% |
| hit, random, 1000 x 5000 | 7.79 | 7.61 | -2% | 7.51 | -4% |
| miss, random, 1000 x 5000 | 10.00 | 12.18 | +22% | 12.08 | +21% |
| insert, few, 1000 x 5000 | 5.57 | 5.41 | -3% | 5.40 | -3% |
| hit, few, 1000 x 5000 | 6.16 | 6.42 | +4% | 6.30 | +2% |
| miss, few, 1000 x 5000 | 7.73 | 7.83 | +1% | 7.34 | -5% |
| insert, random, 1000000 x 2 | 23.39 | 22.92 | -2% | 20.57 | -12% |
| hit, random, 1000000 x 2 | 28.70 | 28.46 | -1% | 26.43 | -8% |
| miss, random, 1000000 x 2 | 45.62 | 46.35 | +2% | 43.14 | -5% |
| insert, few, 1000000 x 4 | 5.21 | 5.21 | 0% | 4.33 | -17% |
| hit, few, 1000000 x 4 | 7.79 | 7.74 | -1% | 6.92 | -11% |
| miss, few, 1000000 x 4 | 36.58 | 36.34 | -1% | 32.34 | -12% |

- On a million random keys the three kernels take 5 to 12% less time, here and in a second such run (6 to 9% there). There a probe waits for memory, and the address is ready three cycles sooner.
- The two short rows of a million keys of few values came out between -17% and +20% in three runs, for this change and for one that does not touch `insert`; they say nothing. The misses of that input took 5 and 12% less.
- The misses on a thousand random keys take 21% more time, in every run. So they do with the old probe respelled, which is the old loop with two instructions fewer: that row is fast for the one loop the programs had, by where its branches lie, and tells nothing of the index. In the run of `perf` below it is the fastest it has been.
- Without calls in place the same change gains 3 to 7% on random keys of both sizes.

**The key that `place` has read**, as a second result, so that `lookup` does not load it again, was measured apart, as the backlog asked. With the call it costs up to 7% at a thousand keys and nothing at a million, as the second result goes through a register and a stack slot. With the body in place it gains between nothing and 5% in the ten rows that can be told, and both changes together are within 6% of the index in words alone, sometimes ahead and sometimes behind. It is not in the program: what it saves is a load that follows a store of the same address, which is for the compiler to see.

`perf` on the new program, at its one place, against C: 1.236 in the geometric mean of the twelve kernel times, where it was 1.306 with calls in place and 1.614 before; level with C on the inserts and the misses of a million random keys (1.00 and 1.04) and behind by a factor of 1.8 and 2.1 on the lookups of a thousand keys of few values.

## Tasks, 2026-11-20

`tools/par.aw`, built as `build/tools/par` (until 2026-11-20 the Python script `tools/par.py`, which the figures below were taken with), times the programs of `bench/par/`, each written twice: `<name>_seq.aw` does the work in one call, `<name>_par.aw` cuts it into tasks with `spawn` and `join` (`lib/std/task.aw`). It is the measurement of the backlog item on parallel execution: what the pool gains at 1, 2, 4, 8, 14 and 20 threads, and how small a task may be before its scheduling costs more than it saves.

```
make all tools
build/tools/par              # the three tables below
build/tools/par --check      # small sizes, outputs only: part of make test
build/tools/par --runs 3 collatz
build/tools/par --grid --runs 3   # every number of threads with every number of tasks
```

| program | what it does | why it is here |
|---|---|---|
| `collatz` | adds up the lengths of the Collatz sequences from 1 to 3000000 | even work, no memory |
| `primes` | counts the primes below 3000000 by trial division | the work grows along the range, so equal pieces are unequal work |
| `memsum` | fills 16777216 words (128 MiB) and adds them up sixteen times | bound by memory: page faults, then reading |

- The sequential program reads its size from stdin; the task program reads the size, the number of threads and the number of tasks. Every timed run must print what the sequential program printed.
- "Threads" is the number of threads that run tasks: the program calls `workers` with one less, because the main thread runs tasks in its joins. It joins the tasks last spawned first, which are the ones most likely still in the queue. At 1 thread there is no worker and every task runs inside its `join`.
- A run is a new process, timed from outside, on all processors; the figure is the median of 7 runs.
- The machine has 20 hardware threads on 14 cores: 6 performance cores with two threads each and 8 efficiency cores. Beyond 6 threads a further thread is a slower core or the second thread of a core, so 20 threads cannot give 20 times.

12th Gen Intel Core i7-12700H, Linux 7.0. Elapsed milliseconds with the work cut into 160 tasks, and below each row the sequential time divided by it:

| program | sequential | 1 | 2 | 4 | 8 | 14 | 20 |
|---|---:|---:|---:|---:|---:|---:|---:|
| collatz | 556.8 | 593.3 | 299.6 | 164.9 | 95.9 | 69.4 | 62.1 |
| | | 0.94 | 1.86 | 3.38 | 5.81 | 8.02 | 8.97 |
| primes | 697.6 | 700.8 | 352.6 | 189.3 | 104.0 | 68.7 | 64.2 |
| | | 1.00 | 1.98 | 3.68 | 6.71 | 10.16 | 10.87 |
| memsum | 201.7 | 201.1 | 122.3 | 83.5 | 69.2 | 62.7 | 61.5 |
| | | 1.00 | 1.65 | 2.41 | 2.92 | 3.22 | 3.28 |

- `collatz` and `primes` gain 1.9 to 2.0 times at 2 threads, 3.4 to 3.7 at 4, 5.8 to 6.7 at 8 and 9 to 11 at 20. The gain per thread falls after the six performance cores are taken, as the machine leads one to expect.
- `memsum` reaches 2.4 times at 4 threads and only 3.3 at 20: its limit is memory, not processors.
- At 1 thread the cost of 160 tasks is not measurable. `collatz` is 6% slower there for another reason: its loop stands at another address in the two programs. The sequential program with `lib/std/task.aw` merely compiled in, and no task, takes 580 to 600 ms.

Elapsed milliseconds by the number of tasks the same work is cut into (`memsum` has that many in each of its 17 passes):

| program | threads | sequential | 20 | 200 | 2000 | 20000 | 200000 | 1000000 |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| collatz | 1 | 556.8 | 584.5 | 589.1 | 610.1 | 619.9 | 628.4 | 681.7 |
| collatz | 20 | 556.8 | 60.8 | 60.2 | 58.8 | 62.8 | 225.4 | 1190.6 |
| primes | 1 | 697.6 | 691.6 | 694.6 | 702.5 | 700.5 | 717.8 | 750.3 |
| primes | 20 | 697.6 | 80.8 | 73.2 | 69.3 | 62.2 | 195.0 | 1176.3 |
| memsum | 1 | 201.7 | 208.8 | 205.3 | 213.7 | 257.6 | 400.4 | 926.2 |
| memsum | 20 | 201.7 | 63.2 | 62.5 | 64.0 | 351.6 | 3389.7 | 16825.1 |

- **A task costs about 0.1 microseconds on one thread**: a million tasks add 97 ms to `collatz` and 59 ms to `primes`. That is `spawn` and `join` with no worker: a record from the free list, the queue, a lock taken three times, no system call.
- **With 20 threads a task costs about 1.2 microseconds** once tasks are so small that the threads meet at the lock: a million tasks take 1.2 s in both programs, about twice the sequential time, for work of 0.6 to 0.7 microseconds a task.
- **Where it turns**: tasks of about 30 microseconds and more (20000 tasks) run at full speed, 9 to 11 times. Tasks of 3 microseconds (200000) still gain 2.5 to 3.6 times. Below about 1 microsecond a task the program is slower than without tasks. `memsum` turns earlier, since its 20000 tasks a pass are 0.6 microseconds each.
- The rule that follows: cut the work into some tens of tasks per thread, not into its smallest pieces. A hundred or a thousand tasks cost nothing and even out unequal work.

### The size of a task at each number of threads

The two tables above vary the threads at one number of tasks and the tasks at 1 and 20 threads. `build/tools/par --grid` times every pair, to say how large a task should be before anything in the pool is changed. Measured on 2026-11-20 with the compiler as it then was, median of 3 runs; a cell is the sequential time divided by the time with tasks, so 1 is no gain. The number of tasks is that of the whole work for `collatz` and `primes` and that of each of the 17 passes for `memsum`.

collatz, sequential 552.0 ms: that time divided by the time with tasks, median of 3 runs

| tasks | 1 | 2 | 4 | 8 | 14 | 20 |
|---:|---:|---:|---:|---:|---:|---:|
| 20 | 0.99 | 1.86 | 3.43 | 5.70 | 7.24 | 8.57 |
| 200 | 0.98 | 1.93 | 3.56 | 6.25 | 8.98 | 9.92 |
| 2000 | 0.98 | 1.92 | 3.54 | 6.27 | 9.24 | 10.05 |
| 20000 | 0.98 | 1.91 | 3.50 | 6.16 | 8.58 | 9.83 |
| 200000 | 0.96 | 1.85 | 3.19 | 5.06 | 4.29 | 2.23 |

primes, sequential 695.6 ms: that time divided by the time with tasks, median of 3 runs

| tasks | 1 | 2 | 4 | 8 | 14 | 20 |
|---:|---:|---:|---:|---:|---:|---:|
| 20 | 1.01 | 1.93 | 3.49 | 5.75 | 7.85 | 9.11 |
| 200 | 1.00 | 1.98 | 3.68 | 6.68 | 10.42 | 10.90 |
| 2000 | 1.00 | 1.97 | 3.70 | 6.89 | 10.89 | 11.39 |
| 20000 | 1.01 | 1.98 | 3.67 | 6.84 | 11.21 | 11.18 |
| 200000 | 0.99 | 1.90 | 3.43 | 5.55 | 4.94 | 3.48 |

memsum, sequential 188.8 ms: that time divided by the time with tasks, median of 3 runs

| tasks | 1 | 2 | 4 | 8 | 14 | 20 |
|---:|---:|---:|---:|---:|---:|---:|
| 20 | 1.01 | 1.56 | 2.31 | 2.65 | 2.81 | 2.93 |
| 200 | 0.96 | 1.41 | 2.26 | 2.95 | 3.08 | 3.12 |
| 2000 | 1.00 | 1.64 | 2.51 | 2.79 | 3.04 | 3.00 |
| 20000 | 0.87 | 1.47 | 1.56 | 0.64 | 0.54 | 0.52 |
| 200000 | 0.51 | 0.33 | 0.10 | 0.07 | 0.06 | 0.06 |

What a task is in time follows from the sequential time: in `collatz` 27.6 ms at 20 tasks, 2.8 ms at 200, 276 microseconds at 2000, 28 at 20000 and 2.8 at 200000; in `primes` a quarter more; in `memsum`, a pass being 11 ms, 555 microseconds at 20 tasks, 55 at 200, 5.5 at 2000, 0.55 at 20000 and 0.055 at 200000.

- **Work bound by the processor: tasks of 30 microseconds to 3 milliseconds.** From 200 to 20000 tasks the gain is the same within a tenth at every number of threads, 9.8 to 11.4 times at 20. That is ten to a thousand tasks for each thread.
- **One task for each thread is too few.** 20 tasks at 20 threads give 8.6 and 9.1 times where 2000 give 10.1 and 11.4, and at 14 threads 7.2 and 7.9 against 9.2 and 10.9: the cores are not equally fast and, in `primes`, the pieces not equally long, and with one piece each nothing evens that out.
- **Tasks of 3 microseconds are worth it up to 8 threads and not beyond.** At 200000 tasks the gain at 8 threads is 5.1 and 5.6 against 6.3 and 6.9, a fifth less; at 14 threads it falls to 4.3 and 4.9 and at 20 to 2.2 and 3.5, what 4 threads give or less, since the threads then wait for each other at the queue.
- **Work bound by memory: tasks of 5 microseconds or more, and few threads.** `memsum` gains 2.3 to 2.5 times at 4 threads and 2.9 to 3.1 at 20 with 20 to 2000 tasks a pass; more threads than the memory can feed buy little. At 0.55 microseconds a task it is slower than without tasks from 8 threads on, and at 0.055 at every number of threads.
- **A starting size**: about ten times as many tasks as threads, each of at least 100 microseconds; make them smaller only if the pieces are unequal, and not below 30 microseconds when more than 8 threads run them. The pool needs no change for any of this: within those sizes its cost cannot be measured.

Source tokens (`o200k_base`), comments left out:

| program | sequential | tasks | lines added |
|---|---:|---:|---:|
| collatz | 109 | 230 | 24 |
| primes | 115 | 236 | 24 |
| memsum | 153 | 278 | 22 |

The task form of a program is 121 to 125 tokens and 22 to 24 lines longer; before the programs were indented it was 107 to 115. In `collatz`, 90 of those 115 are the two loops that cut the range into pieces, keep the handles and add up the results, `spawn` and `join` being 12 of those; the `use` is 6, `call workers` 6, and the rest declares the buffer of handles and reads the two numbers. [The token page](tokens.md#tasks-measured-on-2026-11-20) has the count of the forms.

## Async, 2026-11-20

The programs of `bench/async/` measure the runtime of `lib/async/`, which polls every task on one thread. They are the measurement of the backlog section on asynchronous execution: what a wait costs, what a server on one thread carries, and whether a scheduler on several threads would pay. Machine as above (Core i7-12700H, 20 threads, Linux 7.0); every figure is the median of five runs. The table of the server and the times of its processor were taken again after both servers were made to write without `SIGPIPE`.

```
make
for p in timer echod ping switch mem hop; do build/ailc < bench/async/$p.aw > build/$p && chmod +x build/$p; done
build/switch 1000 1000      # a million waits
build/mem 100000            # bytes a sleeping task
build/hop 100000            # work handed to the pool, one after the other
build/ping 100 2000         # server and clients in one process
build/echod 0 & build/ping 100 2000 <port>
gcc -O2 -o build/echod_c bench/async/echod.c
```

| program | what it does |
|---|---|
| `switch` | tasks that each `await asyncyield` many times: a task made, the asking task put aside, both polled, a record given back |
| `mem` | tasks asleep in a timer, three variables alive in each; resident memory before and after |
| `hop` | `await asyncblock` of a function that does nothing |
| `echod` | a TCP echo server, one task a connection, 4096 bytes of buffer in each task |
| `ping` | load for it: a task a connection sends 64 bytes and reads them back, and times each round trip |
| `echod.c` | the same server on `epoll` by hand in C, one thread, for comparison |

**A wait.**

| | nanoseconds |
|---|---:|
| a wait that gives the thread to another task and gets it back (`switch`, 1000 tasks) | 22 |
| the same with 100000 tasks, whose records no longer fit the cache | 43 |
| work handed to the pool and awaited, 100000 pieces at once (`hop`) | 1100 |
| the same one after the other, the polling thread asleep for each | 3500 |

A wait on the thread is a few tens of instructions: a record from a free list, two entries in a queue, two indirect calls. Handing work to another thread costs 50 to 160 times that, a futex wake for the worker and, if the polling thread sleeps, an `eventfd` write and an `epoll_wait` to get back: `asyncblock` is for work of many microseconds, and the figures for `spawn` above hold for it.

**Memory.** A task asleep in a timer is 272 bytes of resident memory: its record of 128 bytes (64 of the library, the rest for its variables, three here), the timer's event of 128 and 16 in the heap. A connection of `echod` is a record of 8192 bytes, of which 4096 are its buffer and the rest the rounding to a power of two, plus 128 for the descriptor. A hundred thousand waiting tasks are 27 MiB, and none has a stack.

**A server.** Round trips of 64 bytes over loopback, `ping` in one process and the server in another, so each has a processor:

| connections | server | round trips a second | mean | median | 99 of 100 within |
|---:|---|---:|---:|---:|---:|
| 1 | `echod` (AIL) | 119200 | 7 us | 7 us | 17 us |
| 1 | `echod.c` | 120200 | 7 us | 7 us | 16 us |
| 100 | `echod` (AIL) | 297600 | 334 us | 334 us | 381 us |
| 100 | `echod.c` | 295200 | 337 us | 336 us | 433 us |
| 1000 | `echod` (AIL) | 285000 | 3428 us | 3363 us | 4415 us |
| 1000 | `echod.c` | 281500 | 3469 us | 3372 us | 4734 us |

With clients and server as tasks of one thread (`ping` without a port) the same work is 165800, 171700 and 158600 round trips a second, about half, as one processor does both sides.

- **The server written with `async fn` carries what the one written on `epoll` by hand carries.** The time of a round trip with many connections is the wait in line, connections times the service time, and the same for both.
- **Where the time goes**: for a million round trips at 100 connections the AIL server uses 0.31 s in its own code and 2.98 s in the kernel; the C server 0.15 s and 2.93 s. The runtime costs 160 ns a round trip more than code written for this one program, 7% of the processor time of the server, and nine tenths of the time are the kernel's in either.
- **System calls**: for 200000 round trips the AIL server made 208705 calls of `read`, 8605 of which said "not now", 200002 writes (`sendto` with `MSG_NOSIGNAL`, counted when they were still `write`) and 169 of `epoll_wait`. An operation asks the kernel first, so a busy server hardly ever sleeps; the server in C, which waits for an event before each read, cannot make fewer.
- The source of the server is 259 tokens in AIL (`echo.aw` and `echod.aw`, with the reading of the port) and 503 in C; [token cost](tokens.md) has the forms.

### A scheduler on several threads

Tokio polls tasks on a pool of threads, each with a queue of its own that idle threads steal from. The backlog asks whether AIL should, once the single thread is measured.

*What could move.* A task between two polls is its record and nothing else: the variables and locals are in it, the place to go on is in it, and no stack belongs to it. The code the compiler writes for an `async fn` does not care which thread calls it. So a task could be polled by another thread than the last time. Everything around it could not, as it is: the queue, the free lists of records, the heap of timers, the records and lists of the descriptors, the list of what can be cancelled and the cells of a task that say who awaits it are written without a lock or an atomic instruction, by the one thread that polls. An event belongs to the driver of the thread that made it.

*What it would cost.* Every wake, every `await` and every operation on a descriptor would take a lock or become a loop around `cas`, which is paid by programs that never use a second thread, on a wait that costs 22 ns now. The hop measured above is what a task that is woken on another thread pays, 1.1 to 3.5 microseconds, more than a round trip's worth of the runtime's own work. And the larger cost is not in the library: today nothing in a program runs at the same time as anything else, so tasks share `var` cells and buffers freely, which is most of what makes them short to write. With tasks on several threads every program would need `xadd` and `cas` where tasks meet, as code for `spawn` does.

*What it would gain.* The server above spends nine tenths of its time in the kernel, and that part does run in parallel. A second thread would raise what one process carries past the 280000 round trips a second of one processor. Work that computes is already parallel: `asyncblock` gives it to the pool.

*The decision.* No scheduler on several threads for now. One thread carries as much as a server in C on one thread, its limit is the kernel's work on one processor, and the programs stay free of locks. If a program needs more than that, the form to build is a thread per processor with a runtime of its own each, tasks never moving, connections spread by the kernel (`SO_REUSEPORT`): it keeps the rule that the tasks of one runtime never run at the same time. Processes do that today without a change, each started with its own copy of the server; for threads the state of the library would have to be found through the thread instead of in a `buf`, which needs a thread-local cell that the language does not have. Work stealing would be the step after, and only measurement of such a program could ask for it.

## Numerical arrays, 2026-11-21

*The kernel times of this section were taken with the clock as a system call in AIL, which adds 78 ns a run to AIL alone: rows of a thousand runs or more overstate AIL / C ([the clock of the harness](#the-clock-of-the-harness-2026-11-21)).*

`lib/nd/` is arrays of numbers on the model of NumPy ([the nd library](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#the-nd-library)). Three programs time its kernels by the method of the [algorithm programs](#algorithms-2026-11-20): `ndelem` adds and multiplies two arrays element by element and multiplies one by a number, `ndreduce` sums a square of numbers, sums its columns and takes a dot product, `ndmatmul` multiplies two square matrices. In AIL a kernel makes arrays of the memory the harness gives it (`ndwrap`) and calls the library, so what is timed is the library as a program uses it; in C and Rust the kernel is a plain loop over the same doubles in the same order.

The harness gives every language the same doubles (`algonum`: the lowest 20 bits of a word of splitmix64, divided by 1024, less 512), and the kernels add in the same order, so the result is the same bytes in the three, which `perf` requires before it times anything. That the results are right is checked apart: `tests/algo.sh` computes them in awk, and the tests `lib_nd_ref_*` compare the library with what NumPy 2.3.5 computed for arrays of `tests/nd/gen.py`. The counts are a thousand and a million numbers for `ndelem` and sides of 100 and 1000 for the two others.

The time of the kernels (same machine, CPU and compilers as above):

| program, input, count x runs | AIL | C | Rust | AIL / C |
|---|---:|---:|---:|---:|
| ndelem add, random, 1000 x 20000 | 15.79 (15.73, 15.91) | 2.62 (2.58, 2.63) | 2.63 (2.56, 2.70) | 6.035 slow |
| ndelem mul, random, 1000 x 20000 | 16.12 (16.02, 16.32) | 2.58 (2.57, 2.61) | 2.49 (2.43, 2.55) | 6.240 slow |
| ndelem scale, random, 1000 x 20000 | 24.13 (23.98, 24.38) | 2.34 (2.33, 2.35) | 1.94 (1.93, 1.99) | 10.322 slow |
| ndelem add, random, 1000000 x 20 | 16.36 (16.00, 17.19) | 13.15 (12.79, 14.03) | 12.62 (12.21, 13.42) | 1.244 slow |
| ndelem mul, random, 1000000 x 20 | 17.02 (16.68, 17.68) | 13.48 (12.87, 13.72) | 13.45 (12.98, 14.05) | 1.262 slow |
| ndelem scale, random, 1000000 x 20 | 20.58 (20.34, 21.19) | 11.54 (11.11, 11.85) | 11.43 (10.87, 11.97) | 1.784 slow |
| ndreduce sum, random, 100 x 1000 | 15.51 (15.47, 15.58) | 4.41 (4.40, 4.45) | 4.40 (4.39, 4.44) | 3.522 slow |
| ndreduce cols, random, 100 x 1000 | 7.44 (7.41, 7.45) | 1.33 (1.33, 1.34) | 1.41 (1.41, 1.43) | 5.582 slow |
| ndreduce dot, random, 100 x 1000 | 15.50 (15.46, 15.56) | 4.41 (4.39, 4.47) | 4.42 (4.40, 4.46) | 3.514 slow |
| ndreduce sum, random, 1000 x 20 | 31.43 (31.24, 31.76) | 9.40 (9.35, 9.49) | 9.47 (9.39, 9.71) | 3.345 slow |
| ndreduce cols, random, 1000 x 20 | 12.79 (12.64, 13.26) | 3.59 (3.48, 3.63) | 3.74 (3.65, 3.84) | 3.564 slow |
| ndreduce dot, random, 1000 x 20 | 32.74 (32.41, 33.18) | 10.22 (10.03, 10.44) | 10.87 (10.72, 11.48) | 3.203 slow |
| ndmatmul, random, 100 x 20 | 17.64 (17.62, 17.73) | 3.45 (3.43, 3.46) | 3.62 (3.61, 3.63) | 5.120 slow |
| ndmatmul, random, 1000 x 1 | 885.04 (883.93, 886.81) | 174.88 (173.84, 179.24) | 174.87 (173.93, 177.36) | 5.061 slow |

Geometric mean of AIL / C over 14 kernel times: 3.650

The elapsed time of the same processes:

| program, input, count x runs | AIL | C | Rust | AIL / C |
|---|---:|---:|---:|---:|
| ndelem, random, 1000 x 20000 | 61.80 (61.39, 62.30) | 8.65 (8.60, 8.72) | 8.37 (8.29, 8.56) | 7.145 slow |
| ndelem, random, 1000000 x 20 | 77.18 (76.02, 78.83) | 60.78 (59.50, 63.19) | 65.90 (64.23, 67.14) | 1.270 slow |
| ndreduce, random, 100 x 1000 | 39.64 (39.57, 39.78) | 11.32 (11.18, 11.48) | 11.67 (11.43, 12.51) | 3.503 slow |
| ndreduce, random, 1000 x 20 | 103.52 (102.78, 104.84) | 50.67 (50.44, 51.15) | 57.08 (56.66, 58.06) | 2.043 slow |
| ndmatmul, random, 100 x 20 | 18.55 (18.50, 18.60) | 4.47 (4.40, 4.54) | 4.78 (4.75, 4.84) | 4.149 slow |
| ndmatmul, random, 1000 x 1 | 907.10 (905.95, 908.32) | 191.28 (190.23, 196.54) | 196.97 (195.79, 199.52) | 4.742 slow |

Geometric mean of AIL / C over 6 inputs: 3.294

NumPy 2.3.5 on the same operations and sizes, in an optional run that is no part of `perf` (`build/tools/nptime`: one thread, a result that is there before the clock starts, the median of 15 rounds; its numbers are not those of the harness, so only the time is compared), beside the kernel times of the table above, in milliseconds:

| kernel, count x runs | AIL | C | NumPy |
|---|---:|---:|---:|
| add, 1000 x 20000 | 15.79 | 2.62 | 7.14 |
| mul, 1000 x 20000 | 16.12 | 2.58 | 7.24 |
| scale, 1000 x 20000 | 24.13 | 2.34 | 9.04 |
| add, 1000000 x 20 | 16.36 | 13.15 | 9.14 |
| mul, 1000000 x 20 | 17.02 | 13.48 | 9.51 |
| scale, 1000000 x 20 | 20.58 | 11.54 | 5.62 |
| sum, 100 x 1000 | 15.51 | 4.41 | 2.08 |
| cols, 100 x 1000 | 7.44 | 1.33 | 2.63 |
| dot, 100 x 1000 | 15.50 | 4.41 | 4.86 |
| sum, 1000 x 20 | 31.43 | 9.40 | 3.13 |
| cols, 1000 x 20 | 12.79 | 3.59 | 3.19 |
| dot, 1000 x 20 | 32.74 | 10.22 | 8.83 |
| matmul, 100 x 20 | 17.64 | 3.45 | 3.11 |
| matmul, 1000 x 1 | 885.04 | 174.88 | 169.92 |

What the figures say:

- The library takes 3.65 times as long as the loops of C in the geometric mean of its kernels, far more than the 1.26 of the algorithm programs. No kernel is within 10% of C.
- Where the memory is the limit the gap nearly closes: adding a million numbers to a million takes 1.24 times as long as in C, a thousand to a thousand 6 times. A thousand numbers stay in the first cache, and there one element costs 0.79 ns in AIL and 0.13 ns in C. The likely cause is that `gcc -O3` adds two doubles with one instruction and several such instructions a round, while the code of `fadd` takes each double from a general register to a floating-point one and back; that is [measured below](#after-the-measurements) with the C loop kept from such instructions.
- `scale`, an array times one number, is 10 times C at a thousand numbers and half as slow again as `add`. The number goes along with a stride of 0, which takes the loop with strides and a choice of the operation for each element, not the loop over words. That is a matter of the library alone, and is [taken up below](#after-the-measurements).
- A sum takes 1.55 ns an element against 0.44 ns in C, and the dot product the same. Each element is added to the one sum before it, so the time is that of one addition after the other: in C an addition of two floating-point registers, in AIL also the two moves between the kinds of registers. The sums of the columns, which go to different elements of the result, take 0.74 ns an element.
- The product of matrices is 5 times C at both sides. Its inner loop is the one of `add` with a multiplication, a row at a time.
- NumPy is faster than the loops of C wherever it adds in more than one place at once, sums above all (0.2 ns an element), and no faster in the product of matrices, 170 ms at a side of 1000 on one thread. A call of `add` on a thousand numbers takes 0.36 microseconds in NumPy, 0.13 in C and 0.79 in the library: NumPy is 2.7 times behind C there, where a call costs it more than the work, and the library 2.2 times behind NumPy.
- A call of the library costs little beside the work: the arrays of a kernel are wrapped again at every run, and at a thousand numbers `add` still spends its time in the loop.

**The sum in pairs.** `ndsumpw` adds halves of halves down to runs of 128 elements, which is what NumPy does for accuracy. Timed in one program on an array of the value 0.1, nanoseconds a call, on a quiet processor:

| elements | `ndsum` | `ndsumpw` |
|---:|---:|---:|
| 1000 | 1620 | 1327 |
| 10000 | 15150 | 10467 |
| 1000000 | 1507559 | 1199275 |

In pairs it is not slower but a fifth to a third faster, though both spend their time in the one loop of `ndacc`. Keeping that loop from being compiled [in place](#calls-compiled-in-place-2026-11-20) in the plain sum changes nothing (1615, 15110 and 1503486 ns), so that is not the cause. The likely one is that a plain sum is one chain of additions, each waiting for the one before, while every run of 128 or fewer starts a chain of its own from 0, and the processor works on the end of one run and the start of the next at once; the gain is largest at 10000 elements, where the runs are 78 long. The error of a million times 0.1 is 1.3e-6 one after the other and 2.3e-10 in pairs ([the order of a sum](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#the-nd-library)). `ndsum` stays the sum in the order of the indices all the same: that order is what a loop in C computes, so its result can be compared byte for byte with one, and it is the same whether the elements lie one behind the other or not. A program that wants the accuracy, or the time, calls `ndsumpw`.

The source of each program, in tokens (`tools/tokens.sh`, without comments). In AIL it is the code of a caller of the library, the wrapping of the arrays and the calls; in C and Rust it is the loops themselves:

| program | AIL | C | Rust |
|---|---:|---:|---:|
| ndelem | 215 | 197 | 200 |
| ndreduce | 252 | 276 | 268 |
| ndmatmul | 141 | 163 | 142 |
| total | 608 | 636 | 610 |
| relative to AIL | 1.00 | 1.05 | 1.00 |

A call of a kernel is 5 to 7 tokens (`call ndadd z x y`), but each array is 12 more before its first use (`buf x 176` and a `call ndwrap x a n*8 1 s`), so three kernels on the same arrays cost what three short loops cost in C. The library was 8252 tokens then (3343, 2314 and 2595 for `array.aw`, `elem.aw` and `reduce.aw`) and is 10259 with the loops added below and `par.aw`, which a program does not pay as source. What a caller gains is in what the loops of C do not do: views, steps, other lengths and the checks, each a call.

### After the measurements

Each of the following was tried on those figures, with the same inputs, the results checked first, and kept only where it gained.

**A number as an operand.** An array of one element went along with a stride of 0 and took the loop that has three strides and chooses the operation for each element. With a loop over words for a number on either side, `scale` on a thousand numbers went from 24.13 ms to 12.45 ms and on a million from 20.58 ms to 12.94 ms. Kept.

**What the vector instructions of C are worth.** The kernels of C built with `gcc -O3 -fno-tree-vectorize`, so that each double is computed alone, beside those of `gcc -O3` and those of AIL with the loop for a number but before the statements below, in milliseconds:

| kernel, count x runs | C | C, one double at a time | AIL |
|---|---:|---:|---:|
| add, 1000 x 20000 | 2.64 | 4.23 | 12.51 |
| mul, 1000 x 20000 | 2.65 | 4.24 | 14.07 |
| scale, 1000 x 20000 | 2.38 | 4.21 | 12.50 |
| sum, 100 x 1000 | 4.43 | 4.47 | 15.50 |
| cols, 100 x 1000 | 1.34 | 3.17 | 7.43 |
| dot, 100 x 1000 | 4.43 | 4.47 | 15.49 |
| matmul, 100 x 20 | 3.21 | 7.24 | 17.80 |

C gains 1.6 to 2.4 times from two doubles an instruction, and nothing in `sum` and `dot`, which add to one sum in order. AIL was 2.3 to 3.5 times behind C that computes one double at a time: the larger part of the gap was not the missing vector instructions but the way of every double through a general register, which the statements on doubles ask for.

**Two doubles at once.** The statements [`padd`, `psub`, `pmul`, `pdiv` and `pmadd`](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#two-doubles-at-once) work from memory to memory on two doubles, and the loops of the library over doubles that lie one behind the other use them wherever each element of the result is computed alone, with the last of an odd count computed as before. The bits are the same, so every check stands as it was. The kernels after it:

| program, input, count x runs | AIL | C | Rust | AIL / C |
|---|---:|---:|---:|---:|
| ndelem add, random, 1000 x 20000 | 9.12 (8.87, 9.17) | 2.79 (2.76, 2.85) | 2.75 (2.73, 2.82) | 3.274 slow |
| ndelem mul, random, 1000 x 20000 | 8.65 (8.62, 8.69) | 2.79 (2.74, 2.83) | 2.61 (2.58, 2.66) | 3.104 slow |
| ndelem scale, random, 1000 x 20000 | 8.17 (8.12, 8.21) | 2.53 (2.47, 2.54) | 2.04 (2.00, 2.06) | 3.231 slow |
| ndelem add, random, 1000000 x 20 | 13.48 (12.80, 14.34) | 12.78 (12.22, 13.84) | 12.30 (11.82, 12.79) | 1.055 |
| ndelem mul, random, 1000000 x 20 | 14.16 (13.50, 14.80) | 12.99 (12.51, 13.60) | 13.05 (12.69, 13.55) | 1.091 |
| ndelem scale, random, 1000000 x 20 | 11.94 (11.40, 12.46) | 11.65 (11.24, 11.90) | 11.29 (11.12, 11.57) | 1.025 |
| ndreduce sum, random, 100 x 1000 | 15.52 (15.49, 15.55) | 4.40 (4.39, 4.44) | 4.39 (4.38, 4.41) | 3.530 slow |
| ndreduce cols, random, 100 x 1000 | 2.88 (2.87, 2.89) | 1.33 (1.32, 1.35) | 1.41 (1.40, 1.41) | 2.169 slow |
| ndreduce dot, random, 100 x 1000 | 15.50 (15.47, 15.61) | 4.40 (4.39, 4.42) | 4.40 (4.39, 4.48) | 3.526 slow |
| ndreduce sum, random, 1000 x 20 | 30.93 (30.80, 31.11) | 8.93 (8.84, 9.07) | 8.93 (8.88, 8.98) | 3.463 slow |
| ndreduce cols, random, 1000 x 20 | 5.04 (4.94, 5.13) | 3.31 (3.26, 3.45) | 3.39 (3.34, 3.49) | 1.520 slow |
| ndreduce dot, random, 1000 x 20 | 31.07 (30.90, 31.17) | 9.18 (9.01, 9.42) | 9.57 (9.50, 9.79) | 3.383 slow |
| ndmatmul, random, 100 x 20 | 5.87 (5.77, 6.44) | 3.15 (3.11, 3.18) | 3.30 (3.28, 3.33) | 1.862 slow |
| ndmatmul, random, 1000 x 1 | 270.16 (265.49, 277.19) | 175.99 (174.71, 176.82) | 176.61 (173.83, 178.22) | 1.535 slow |

Geometric mean of AIL / C over 14 kernel times: 2.175

The elapsed time of the same processes:

| program, input, count x runs | AIL | C | Rust | AIL / C |
|---|---:|---:|---:|---:|
| ndelem, random, 1000 x 20000 | 31.93 (31.50, 32.07) | 9.24 (9.11, 9.37) | 8.84 (8.79, 8.95) | 3.457 slow |
| ndelem, random, 1000000 x 20 | 62.16 (60.72, 64.23) | 58.81 (58.02, 61.66) | 63.13 (62.60, 64.62) | 1.057 |
| ndreduce, random, 100 x 1000 | 35.08 (34.99, 35.32) | 11.20 (11.12, 11.37) | 11.42 (11.35, 11.80) | 3.131 slow |
| ndreduce, random, 1000 x 20 | 92.18 (91.90, 92.74) | 46.15 (45.55, 47.16) | 51.97 (51.56, 52.28) | 1.997 slow |
| ndmatmul, random, 100 x 20 | 6.81 (6.66, 7.29) | 4.16 (4.13, 4.28) | 4.48 (4.45, 4.51) | 1.637 slow |
| ndmatmul, random, 1000 x 1 | 292.02 (286.99, 298.99) | 192.61 (190.32, 193.24) | 197.97 (195.14, 199.62) | 1.516 slow |

Geometric mean of AIL / C over 6 inputs: 1.960

From 3.650 to 2.175 over the kernels. The product of matrices takes a third of its time, the sums of columns two fifths, the arithmetic on a thousand numbers somewhat over half, and on a million numbers the library is within 10% of C. `sum` and `dot` are where they were, 3.5 times C: their elements are added to one sum in the order of the indices, and two sums side by side would be another order and other last digits. That is the price of the order the library promises; the sum in pairs promises none and could take the statements, which no program has asked for yet.

**Blocks in the product.** With the middle loop of the product cut into blocks of rows of the second matrix, so that a block stays in the cache while every row of the result takes its part of it, the order of the additions is the same. The kernel in milliseconds, the median of nine processes, for sides 1000, 300 and 100 with their runs:

| rows in a block | 1000 x 1 | 300 x 3 | 100 x 20 |
|---:|---:|---:|---:|
| none | 262.10 | 23.94 | 5.82 |
| 8 | 255.25 | 22.50 | 5.54 |
| 16 | 253.54 | 23.13 | 5.39 |
| 32 | 252.47 | 23.48 | 5.38 |
| 64 | 255.54 | 23.76 | 5.58 |
| 128 | 258.32 | 24.27 | 5.83 |
| 256 | 284.88 | 24.06 | 5.95 |

4% at best at a side of 1000 and within the variation below it: the loop spends its time computing, 0.27 ns for a product and a sum, not waiting for memory. Not kept.

**Tasks in the product.** `ndmatmulp` of `lib/nd/par.aw` cuts the rows of the result into runs and makes a task of each ([tasks](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#tasks)); no two write the same element, and the result is the same bits. The best of five calls in microseconds on the 20 processors of the machine, the pool limited to the threads given, the calling thread computing as well:

| threads, tasks | side 1000 | side 300 | side 100 | side 30 |
|---|---:|---:|---:|---:|
| `ndmatmul` | 245819 | 11223 | 418 | 16 |
| 1, 1 | 237574 | 7337 | 375 | 15 |
| 1, 2 | 141427 | 4419 | 266 | 13 |
| 3, 4 | 83642 | 2442 | 184 | 10 |
| 7, 8 | 61912 | 2098 | 140 | 10 |
| 7, 64 | 56273 | 1051 | 83 | 38 |
| 13, 14 | 54014 | 1401 | 71 | 11 |
| 13, 64 | 46631 | 1044 | 61 | 32 |
| 19, 20 | 42978 | 1048 | 69 | 14 |
| 19, 64 | 47057 | 1129 | 60 | 33 |

A side of 1000 takes 5.5 times less in 20 tasks, a side of 300 seven times less, a side of 100 six times less; the row `ndmatmul` is the first call of each side and one call only, and likely also pays for memory not yet touched. At a side of 30, 27000 products in all, four tasks gain a third and 64 take more than twice the one. As many tasks as threads is the size to start from, and more tasks than that only where the rows are long, as [for the task programs](#the-size-of-a-task-at-each-number-of-threads). Kept, as a function beside `ndmatmul`, which stays on one thread.

**What the workloads do not ask for.** The three programs and the tests use doubles and, for counts, integers of 64 bits; nothing in them would be shorter or faster with elements of 32 bits, which the walk and the kernels could take as a third kind when a program has such data. None selects elements by an array of indices or of truth values, reads or writes a file of NumPy, solves a system or transforms a signal: advanced indexing, `.npy`, linear algebra and FFTs wait for a program that needs one of them, and `.npy` is the likely first, since it is how arrays come from elsewhere. Syntax for arrays is not worth adding on these counts: a kernel is a call of 5 to 7 tokens (`call ndadd z x y`), which `z=x+y` would bring to 5 or fewer, while the 12 tokens that each array costs before its first use stay, and a caller of the library is already at the tokens of the loops of C (608 against 636).

## Array kernels after the first round, 2026-11-21

*The kernel times of this section were taken with the clock as a system call in AIL, which adds 78 ns a run to AIL alone: rows of a thousand runs or more overstate AIL / C ([the clock of the harness](#the-clock-of-the-harness-2026-11-21)).*

Five questions that the measurements above left, each with the same method: the same inputs, the results checked before the times, a change kept where it gains.

**The sum in a floating-point register.** `ndsum` and `nddot` were loops of `fadd`, which takes the sum from a general register to a floating-point one and back for every element. The statements [`fsum` and `fdot`](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#a-sum-in-its-order) are that loop as one statement, with the sum in `xmm0` from the first element to the last, the elements added in the same order and a product rounded before it is added: the bits are those of the loop. `ndacc`, which every sum of doubles goes through, is an `fsum`, and `nddot` of arrays that lie one behind the other an `fdot`. The kernels of `ndreduce` before and after, by `build/tools/perf ndreduce`:

| kernel, side x runs | AIL before | AIL after | C | AIL / C after |
|---|---:|---:|---:|---:|
| sum, 100 x 1000 | 15.50 | 4.55 | 4.40 | 1.033 |
| cols, 100 x 1000 | 3.26 | 2.89 | 1.33 | 2.174 |
| dot, 100 x 1000 | 15.50 | 4.52 | 4.41 | 1.025 |
| sum, 1000 x 20 | 31.42 | 8.91 | 8.88 | 1.003 |
| cols, 1000 x 20 | 5.26 | 4.97 | 3.30 | 1.507 |
| dot, 1000 x 20 | 32.73 | 9.12 | 9.10 | 1.003 |

`sum` and `dot` take the time of the loops of C, from 3.3 to 3.5 times it, and the geometric mean of the six kernels goes from 2.772 to 1.232. `cols` does not use the statements (its two runs differ within the variation of that kernel between runs). The sum in pairs, `ndsumpw`, adds its runs of 128 elements through `ndacc` and gains with it. Kept.

**Arrays taken backwards.** A view with the step -1 has a stride of -8, and its runs took the loop with three strides that chooses the operation for each element. `ndrun` now turns a run round when the result goes backwards and each of the two others does too or is one element: it starts at the lowest address and takes the loops for arrays that lie one behind the other, two doubles at a time. Each element is computed alone, so the bits are the same, also for a result that is one of the two. The kernels of `ndelem` on such views of its three arrays, the median of nine processes in milliseconds, beside the arrays forwards:

| kernel, count x runs | before | after | forwards |
|---|---:|---:|---:|
| add, 1000 x 20000 | 32.69 | 8.17 | 8.41 |
| mul, 1000 x 20000 | 31.13 | 8.44 | 8.15 |
| scale, 1000 x 20000 | 29.54 | 7.80 | 7.67 |
| add, 1000000 x 20 | 28.27 | 13.47 | 13.54 |
| mul, 1000000 x 20 | 28.42 | 14.32 | 14.34 |
| scale, 1000000 x 20 | 27.72 | 12.20 | 12.16 |

3.7 to 4.0 times less at a thousand numbers, 2.0 to 2.3 times less at a million, and the time of the arrays forwards. A result backwards of arrays forwards, and any other mix of strides, goes one element at a time as before. Kept.

**What a call costs before its loop.** A call of `ndadd` checks kinds, lengths and shared storage and starts a walk before the first element, and the programs of the harness wrap their three arrays (`ndwrap`) inside the timed region at every run. `ndelem` now takes a short way first: for the four operations on doubles, a result of one axis whose elements lie one behind the other, and two others that are each like it or without axes and apart from it by their addresses, there is nothing to walk, to broadcast or to refuse, and `ndrun` is called at once. Everything else, the result in the place of one of the two among it, takes the checks as before.

The clock of the harness has to be taken off to see it: in AIL it is a system call, and a kernel that does nothing takes 91 ns a run there against 12 ns in C, whose library reads the clock without one. The times of a call in nanoseconds with that taken off, one process of a million runs, the median of nine, with the three `ndwrap` inside the clock and outside it (the 176 bytes used again), and for an array times a number (`ndscalar` inside, the array's 176 bytes used again):

| elements | wrap inside, before | after | wrap outside, before | after | times a number, before | after |
|---:|---:|---:|---:|---:|---:|---:|
| 1 | 83 | 42 | 56 | 18 | 66 | 19 |
| 16 | 90 | 43 | 64 | 19 | 75 | 27 |
| 100 | 110 | 66 | 81 | 43 | 90 | 41 |
| 1000 | 311 | 263 | 284 | 241 | 302 | 238 |

The checks were 40 ns of a call and are a few; the three `ndwrap` are 25 ns and stay. At 16 elements a call with its arrays ready takes 19 ns instead of 64, and at a thousand 15% less; using the 176 bytes again saves another 8% there. The loop of C on a thousand takes 116 ns by the same reckoning, so the library is at 2.1 to 2.3 times C at that size, and the plain loop of `load`, `fadd` and `store` in AIL takes 526 ns. Skipping the division in `ndcount` for small lengths was tried for the `ndwrap` and gained nothing.

The rows of `perf` for a thousand numbers carry the 91 ns of the clock 20000 times, 1.8 ms of each kernel time in AIL against 0.24 ms in C, so they overstate the ratio; they are left as measured, since every table above has them so. After this change:

| program, input, count x runs | AIL | C | Rust | AIL / C |
|---|---:|---:|---:|---:|
| ndelem add, random, 1000 x 20000 | 7.58 (7.55, 7.63) | 2.81 (2.78, 2.86) | 2.75 (2.71, 2.83) | 2.693 slow |
| ndelem mul, random, 1000 x 20000 | 7.88 (7.82, 7.99) | 2.81 (2.77, 2.84) | 2.61 (2.58, 2.71) | 2.806 slow |
| ndelem scale, random, 1000 x 20000 | 7.47 (7.41, 7.52) | 2.52 (2.50, 2.55) | 2.03 (2.01, 2.05) | 2.962 slow |
| ndelem add, random, 1000000 x 20 | 13.88 (13.54, 15.07) | 14.14 (13.37, 15.10) | 13.30 (12.73, 13.80) | 0.982 |
| ndelem mul, random, 1000000 x 20 | 14.64 (14.41, 15.31) | 14.09 (13.73, 15.01) | 13.87 (13.71, 14.21) | 1.039 |
| ndelem scale, random, 1000000 x 20 | 12.13 (11.75, 12.59) | 12.10 (11.52, 12.51) | 11.65 (11.31, 12.15) | 1.002 |

Geometric mean of AIL / C over 6 kernel times: 1.685, from 1.840 over the same six rows of the first round. Kept.

**Eight doubles a round in the product.** The loop of `ndaxpy`, which adds a number times a row to a row of the result, took one `pmadd` and one test a round. It now takes four `pmadd`, eight doubles, while a row has eight left, then pairs, then the last of an odd count. Each element of the result still gets its products in the order of `k`, so the bits are the same. The kernel of `ndmatmul` in milliseconds, the median of nine processes:

| side x runs | before | after | less |
|---|---:|---:|---:|
| 8 x 1000 | 0.90 | 0.84 | 7% |
| 16 x 1000 | 3.26 | 3.05 | 6% |
| 30 x 1000 | 12.98 | 12.96 | 0% |
| 64 x 1000 | 99.21 | 83.67 | 16% |
| 100 x 20 | 6.74 | 5.47 | 19% |
| 300 x 3 | 24.72 | 19.27 | 22% |
| 1000 x 1 | 269.37 | 238.11 | 12% |

A fifth less where the matrices fit the cache and the loop is what costs, less at a side of 1000, where memory has its part, and nothing at 30, whose rows are three rounds and three pairs. `build/tools/perf ndmatmul` then gives 1.769 times C at a side of 100 and 1.323 at 1000, from 1.862 and 1.535. Kept.

**How many tasks for a product.** `bench/nd/mmtasks.aw` times `ndmatmul` and `ndmatmulp` in one process for a shape, a number of threads and a number of tasks, with the results written once before any clock; `bench/nd/mmtasks.sh` runs it for the table. A cell is the first call of `ndmatmulp`, which also starts the threads of the pool, and then the fastest of the later calls, which find them there, in microseconds, the median of five processes; the row `ndmatmul` is the fastest call on one thread, the median of all processes of the shape. It is timed first in its process, and at the smallest shape it varied from 13 to 21 between processes, likely a processor not yet at its speed; the row `1, 1`, the same loop timed later, is the one to compare with there. A shape is the rows and columns of the first matrix and the columns of the second, so the first number is the rows of the result, which are what the tasks share out:

| threads, tasks | 30 x 30 x 30 | 100 x 100 x 100 | 300 x 300 x 300 | 1000 x 1000 x 1000 | 4000 x 16 x 16 | 16 x 250 x 250 | 250 x 250 x 16 |
|---|---:|---:|---:|---:|---:|---:|---:|
| `ndmatmul` | 20 | 297 | 6346 | 223890 | 699 | 242 | 642 |
| 1, 1 | 14, 13 | 328, 299 | 6462, 6257 | 221941, 221936 | 701, 697 | 239, 230 | 643, 641 |
| 2, 2 | 62, 14 | 400, 176 | 5125, 3574 | 129074, 122963 | 654, 391 | 301, 141 | 605, 346 |
| 4, 4 | 81, 11 | 285, 143 | 3324, 2957 | 70601, 72065 | 485, 268 | 240, 119 | 473, 225 |
| 8, 8 | 120, 9 | 238, 85 | 1981, 1701 | 51048, 51616 | 361, 161 | 202, 66 | 342, 120 |
| 14, 14 | 122, 9 | 240, 65 | 1402, 1200 | 40490, 43611 | 353, 142 | 213, 69 | 319, 92 |
| 20, 20 | 128, 11 | 221, 56 | 1584, 1459 | 29680, 39477 | 329, 122 | 220, 59 | 336, 79 |
| 4, 16 | 104, 13 | 223, 125 | 3109, 2414 | 75650, 75699 | 410, 268 | 265, 116 | 395, 215 |
| 8, 32 | 177, 16 | 207, 78 | 1604, 1332 | 47720, 49726 | 362, 167 | 214, 84 | 315, 120 |
| 20, 64 | 184, 26 | 261, 53 | 1243, 960 | 34566, 38559 | 374, 118 | 233, 59 | 304, 77 |

- **With the pool there**, tasks pay from the smallest product measured: 27000 products and sums, 13 microseconds, take 9 in 8 tasks. That is where few tasks are right: 20 tasks are no faster than 4 there, and 64 take twice the time of one thread, each task being 0.2 microseconds of work behind a lock. From a million products on, as many tasks as threads is the size, 5 to 8 times faster on 20 threads.
- **The first call** starts the threads, about 60 microseconds for the first and 120 to 180 for eight or more. It is slower than one thread up to a side of 100 and gains from there: a program that multiplies once should use tasks from some hundred microseconds of work, a program that multiplies often from ten.
- **More tasks than threads** gain where a task would otherwise be milliseconds: a side of 300 takes 960 microseconds in 64 tasks against 1459 in 20, since the threads of the machine are not equally fast (6 cores of one kind and 8 of another) and short tasks even that out. At a side of 1000 and below a side of 300 it gains nothing.
- **The shape** counts through the rows of the result, since a task is a run of rows: 16 rows of 250 are at most 16 tasks, 3.9 times faster, where 250 rows of 16, as many products in all, are 8.3 times faster. Many short rows (4000 by 16 by 16) share out as well as a square, 5.9 times.

**Tasks asked for and row blocks.** `ndmatmulp` cuts the rows of the result into runs of the rows divided by the tasks, rounded up, and the last run is what is left. A run is a row block, computed as one piece, and `ndruns` returns their number. Where the rows do not go round that is fewer blocks than tasks asked for, which the table above does not show; `bench/nd/mmtasks.sh` prints both numbers, the tasks asked for and the row blocks that `ndruns` plans. The number is the plan and no count of tasks that ran: with one block (0 or 1 task asked for, or one row) the caller computes it and nothing is spawned, and a block whose `spawn` fails is left to the caller as well. `mmtasks` prints 1 for a call on one thread and task and 8 for 16 rows with 14 tasks asked for. The row blocks for the rows and the tasks of that table:

| rows of the result | 14 asked | 16 | 20 | 32 | 64 |
|---|---:|---:|---:|---:|---:|
| 16 | 8 | 16 | 16 | 16 | 16 |
| 30 | 10 | 15 | 15 | 30 | 30 |
| 100 | 13 | 15 | 20 | 25 | 50 |
| 250 | 14 | 16 | 20 | 32 | 63 |
| 300 | 14 | 16 | 20 | 30 | 60 |
| 1000 | 14 | 16 | 20 | 32 | 63 |
| 4000 | 14 | 16 | 20 | 32 | 64 |

With 2, 4 and 8 tasks there is a block for every one asked for. The split was measured against a balanced one, the rows divided by the tasks rounded down with one row more in the first runs, which gives a block for every task asked for. The fastest later call in microseconds, the median of nine processes of each, run in turn, for the cells where the two plan other blocks:

| rows x columns x columns | threads, tasks asked | row blocks | runs rounded up | balanced | balanced / rounded up |
|---|---|---|---:|---:|---:|
| 30 x 30 x 30 | 14, 14 | 10, 14 | 8.7 | 9.8 | 1.13 |
| 30 x 30 x 30 | 20, 20 | 15, 20 | 11.1 | 17.5 | 1.58 |
| 30 x 30 x 30 | 4, 16 | 15, 16 | 10.6 | 10.5 | 0.99 |
| 16 x 250 x 250 | 14, 14 | 8, 14 | 71.2 | 57.8 | 0.81 |
| 100 x 100 x 100 | 14, 14 | 13, 14 | 65.3 | 59.4 | 0.91 |
| 100 x 100 x 100 | 4, 16 | 15, 16 | 122.0 | 114.0 | 0.93 |
| 100 x 100 x 100 | 8, 32 | 25, 32 | 72.8 | 63.6 | 0.87 |
| 100 x 100 x 100 | 20, 64 | 50, 64 | 50.8 | 55.3 | 1.09 |
| 250 x 250 x 16 | 20, 64 | 63, 64 | 76.9 | 74.9 | 0.97 |
| 300 x 300 x 300 | 8, 32 | 30, 32 | 1318.9 | 1258.2 | 0.95 |
| 300 x 300 x 300 | 20, 64 | 60, 64 | 916.5 | 902.6 | 0.98 |

The balanced split is faster where each of its tasks is ten microseconds of work or more and there is a thread for it: 16 rows of 250 on 14 threads take 58 microseconds in 14 tasks against 71 in 8. It is slower where a task is a microsecond, the product of side 30, since there the tasks it adds are what costs, and with 64 tasks on 20 threads at a side of 100. The geometric mean of the eleven ratios is 1.00, and in the 31 cells where both plan the same blocks the two differ by what the runs differ. So the split stays: a caller who wants a task for every thread asks for a number the rows go round with, and `ndruns` says what a number gives.

`ndmatmulp` is left as it is: the caller gives the number of tasks, and these figures say which.

## The sum in pairs, two doubles at a time, 2026-11-21

`ndsumpw` promises no order within a run of 128 elements, so a run of doubles that lie one behind the other need not be one chain of additions. `ndpack` adds it with `padd`, which adds two doubles in memory to two in memory. One `padd` after the other on the same two sums is no gain: each waits for the store of the one before to come back as a load, about as long as two additions. The sums are therefore sixteen, eight `padd` a round on eight pairs of them, and added in halves at the end. An array of the value 0.1, nanoseconds a call, the best of seven rounds on one processor:

| elements | `ndsum` | `ndsumpw` with `fsum` | four sums | sixteen sums |
|---:|---:|---:|---:|---:|
| 1000 | 448 | 414 | 367 | 271 |
| 10000 | 4385 | 3856 | 4401 | 3279 |
| 1000000 | 439458 | 327931 | 342890 | 265725 |

With sixteen sums a call takes 0.61 to 0.75 of the time of `ndsum` and 0.66 to 0.85 of what it took with `fsum`; with four it was slower than `fsum` from 10000 elements on, which is why that form was not kept. The result is other bits than before: a million times 0.1 was 2.3e-10 below 100000 and is now 100000. Sums whose every step is exact are the same in any order, and `tests/lib_nd_sum` requires that of every count from 1 to 300, with a stride of 16 as well, which goes one at a time. Integers, and doubles with another stride than 8, are added through `ndacc` as before. The program that timed it is not in the repository; it calls each function 200 million elements' worth of times in a loop around `procnow`.

## The clock of the harness, 2026-11-21

The harness of the algorithm programs reads the monotonic clock before and after every kernel run. In AIL that was `sys clock_gettime`, a system call; `clock_gettime` of the C library and `Instant::now` of Rust call `__vdso_clock_gettime`, a function the kernel maps into every process that reads the clock without entering the kernel. A kernel that does nothing, run a million times, shows what a run pays for its two readings, in ns a run (the median of nine processes on one CPU, three kernels a process):

| the clock | AIL | C | Rust |
|---|---:|---:|---:|
| as it was | 91.1 | 12.1 | 12.0 |
| the vDSO called from AIL | 12.9 to 13.5 | 12.1 | 12.0 |
| the system call in all three | 91.0 | 91.0 | 88.9 |

Three ways to make the three equal were compared:

- **Take the clock off afterwards**: time a run around nothing as often as the kernels run and let `perf` subtract it. Two empty kernels of one process differ by 0.1 ns at most, so the subtraction would be exact for a kernel that does nothing; but the entry into the kernel stays in every run of AIL, with what it does to the caches and the predictor of a kernel of a microsecond, and the output of every program, `tests/algo.sh` and `perf` gain a word.
- **The system call in all three** is two lines in C and a declaration in Rust, and equal to within 2 ns; every language then pays 90 ns a run and times short kernels through a system call.
- **The vDSO from AIL** leaves C and Rust as they are and the output as it is. That is what the harness now does.

`algoclk` of `bench/algo/harness.aw` finds the function once, when the five words are read: the address of the vDSO is the value 33 (`AT_SYSINFO_EHDR`) of the auxiliary vector, read from `/proc/self/auxv` since a library has no `args`; the file there is ELF, whose program headers give the distance of the file from its addresses and the dynamic table, which names the symbols, their names and the hash table that has their count; the symbol `__vdso_clock_gettime` is the function. `algonow` calls it with `callp f 0 1 algots`: the values of `callp` after the address go to `rax`, `rdi` and `rsi`, and the last two are where a C function takes its first two. If anything is missing the clock is the system call, called the same way through a function of the harness.

Two things had to hold for the call. The function keeps `rbx`, `rbp` and `r12` to `r15`, as the C convention has it, which are the registers of AIL variables; the others AIL assumes lost over any call. And AIL does not keep the stack at a multiple of 16 bytes at a call, which C code may rely on: the vDSO of Linux 7.0 on x86-64 has no aligned store to the stack (its `movaps` and `movdqa` are in `__vdso_getrandom`, between registers and from its own constants), and `tests/algo_clock` reads the clock from a function with a `local` of 8 bytes and from one without, so with the stack both ways.

What it changes for a kernel, in ms for all runs, with the C program beside:

| program, count, runs | first kernel, system call | vDSO | C | AIL/C before | after |
|---|---:|---:|---:|---:|---:|
| `ndelem`, 1000, 20000 | 7.12 | 5.39 | 2.77 | 2.57 | 1.95 |
| `quicksort`, 100, 20000 | 16.77 | 14.89 | 10.97 | 1.53 | 1.36 |
| `ndelem`, 1000000, 20 | 13.54 | 13.89 | | | |

The 1.7 ms that leave the first row are the 78 ns of difference 20000 times and a little more; at a million elements and 20 runs nothing changes. The tables above this section that have rows of many short runs were measured with the system call.

**The tables again.** `build/tools/perf` was run twice on the six algorithm programs and the three array programs, once with the programs built on the harness as it was and once as it is, the same compiler and the same C and Rust programs both times. The geometric mean of AIL / C over the 62 kernel times is 1.201 where the system call gave 1.266, and over the 42 elapsed times 1.164 where it gave 1.230, since the clock is in the time of the process too. The 30 kernel times of a thousand runs or more, in ms for all runs:

| kernel, input, count x runs | AIL, system call | AIL, vDSO | C | AIL / C, system call | AIL / C, vDSO |
|---|---:|---:|---:|---:|---:|
| quicksort, random, 1000 x 1000 | 23.10 | 21.22 | 13.56 | 1.710 | 1.565 |
| quicksort, sorted, 1000 x 1000 | 13.95 | 13.96 | 10.87 | 1.277 | 1.284 |
| quicksort, reversed, 1000 x 1000 | 11.99 | 11.94 | 8.89 | 1.344 | 1.343 |
| quicksort, few, 1000 x 1000 | 3.96 | 3.88 | 2.71 | 1.456 | 1.432 |
| mergesort, random, 1000 x 1000 | 18.82 | 20.07 | 18.33 | 1.033 | 1.095 |
| mergesort, sorted, 1000 x 1000 | 5.16 | 5.28 | 5.26 | 0.981 | 1.004 |
| mergesort, reversed, 1000 x 1000 | 5.87 | 4.81 | 5.15 | 1.144 | 0.934 |
| mergesort, few, 1000 x 1000 | 10.72 | 10.82 | 8.93 | 1.220 | 1.212 |
| heapsort, random, 1000 x 1000 | 22.52 | 17.81 | 17.25 | 1.297 | 1.032 |
| heapsort, sorted, 1000 x 1000 | 21.25 | 17.88 | 13.24 | 1.607 | 1.350 |
| heapsort, reversed, 1000 x 1000 | 16.60 | 13.08 | 11.11 | 1.490 | 1.177 |
| heapsort, few, 1000 x 1000 | 17.25 | 15.69 | 15.11 | 1.117 | 1.038 |
| bsearch, random, 1000 x 2000 | 54.32 | 47.06 | 54.70 | 1.000 | 0.860 |
| bsearch, few, 1000 x 2000 | 54.99 | 45.17 | 52.73 | 1.053 | 0.857 |
| hashtab insert, random, 1000 x 5000 | 6.43 | 5.37 | 5.49 | 1.178 | 0.978 |
| hashtab hit, random, 1000 x 5000 | 7.35 | 6.73 | 8.63 | 0.854 | 0.780 |
| hashtab miss, random, 1000 x 5000 | 10.26 | 9.27 | 9.40 | 1.072 | 0.986 |
| hashtab insert, few, 1000 x 5000 | 5.19 | 4.94 | 4.22 | 1.239 | 1.171 |
| hashtab hit, few, 1000 x 5000 | 6.10 | 5.67 | 3.38 | 1.810 | 1.678 |
| hashtab miss, few, 1000 x 5000 | 6.88 | 6.65 | 3.45 | 1.988 | 1.928 |
| graph bfs, random, 1000 x 2000 | 15.23 | 13.64 | 10.67 | 1.411 | 1.278 |
| graph dfs, random, 1000 x 2000 | 24.52 | 20.13 | 15.09 | 1.594 | 1.334 |
| graph bfs, few, 1000 x 5000 | 12.47 | 12.27 | 7.99 | 1.543 | 1.536 |
| graph dfs, few, 1000 x 5000 | 14.78 | 14.04 | 7.29 | 2.027 | 1.926 |
| ndelem add, random, 1000 x 20000 | 7.08 | 5.38 | 2.58 | 2.744 | 2.085 |
| ndelem mul, random, 1000 x 20000 | 7.39 | 5.37 | 2.58 | 2.875 | 2.081 |
| ndelem scale, random, 1000 x 20000 | 6.99 | 5.42 | 2.32 | 3.013 | 2.336 |
| ndreduce sum, random, 100 x 1000 | 4.53 | 4.44 | 4.39 | 1.034 | 1.011 |
| ndreduce cols, random, 100 x 1000 | 2.91 | 2.79 | 1.33 | 2.188 | 2.098 |
| ndreduce dot, random, 100 x 1000 | 4.51 | 4.43 | 4.39 | 1.027 | 1.009 |

Over these 30 rows the geometric mean of AIL / C goes from 1.425 to 1.286; over the 32 rows of fewer runs it is 1.133 and 1.126, the same. The clock itself is the runs times 78 ns: 1.6 ms of each `ndelem` row, 0.4 ms of a `hashtab` row, 0.08 ms of a sort. Some rows moved by more than that, `heapsort` by 3 to 5 ms and `bsearch` by 7 to 10, which is no clock: it is what an entry into the kernel before every run does to a kernel of ten microseconds, or the kernels standing elsewhere in the file behind a longer harness, and this run does not tell the two apart ([the move to a library](#the-algorithm-kernels-in-a-library-2026-11-21) measures placement). A few rows went the other way by as much as the runs differ (`mergesort`, random).

The kernel tables of [Algorithms](#algorithms-2026-11-20), [Numerical arrays](#numerical-arrays-2026-11-21) and [Array kernels after the first round](#array-kernels-after-the-first-round-2026-11-21) are left as they were measured, each with a note that its rows of many runs include the system call.

## The algorithm kernels in a library, 2026-11-21

The kernels of the algorithm programs moved from `examples/` to [`lib/algo/`](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#the-algo-library); a program is now `use` lines, at most an adapter and the call of the harness. Three things could have changed with that: the calls compiled in place, the place of the code, and the time.

- **The six harness programs are the same files.** `quicksort`, `mergesort`, `heapsort`, `bsearch`, `hashtab` and `graph` built before and after the move are equal byte for byte (`cmp`). A name is not in the code, the library is used where the kernel stood, behind the harness, and its functions are in the order the kernels had. So `hashplace` is still compiled into `hashfill` and `hashfind`, `sortsift` into `sortheap` and `searchlower` into the loop of the program, since each stands before its caller and names no global; and no kernel time can differ. Timed all the same, a thousand elements a thousand times, the sums of the kernels in ms before and after: `quicksort` 21.20 and 21.17, `mergesort` 20.13 and 20.20, `heapsort` 19.18 and 18.13, `bsearch` 23.87 and 24.03, `hashtab` 1.21, 1.41, 1.88 and 1.14, 1.38, 1.88, `graph` 6.98, 10.39 and 6.92, 10.36. That is what two runs of one file differ by, up to 5% for `heapsort`.
- **`isort` is another program now**: its loop was at top level on a `buf` of the program and is the function `sortins` on an address, and the file carries the three other sorts, 2096 bytes where it was 563. Its elapsed time, the median of nine processes, is 54.3 ms against 54.6.
- **The place of the code** still counts for more than the move. With 0, 8, 24 and 40 bytes of `data` before the code, `isort` with its loop in the program takes 54.8, 55.6, 54.0 and 54.2 ms, and with the loop in the library 54.1, 54.1, 60.1 and 65.1: a fifth more at one placement and nothing at another, as the kernels on a thousand elements showed before. A comparison of two builds of a short loop has to be made over several placements; the move itself costs nothing that this shows.

The tokens of a caller and of the library are in [tokens.md](tokens.md#result): a caller is 30 to 79 tokens where the C program is 156 to 345, and the library, whose names all carry a prefix, 1793 where the kernels in the programs were about 1300.

## Stack slots, 2026-11-21

A variable without a register lives in a slot of the frame, and every use of one was `[rsp+disp32]`, eight bytes where five do; a value that one line stored to a slot was loaded from it by the next. `hashfind` of `lib/algo/hash.aw` had both: `mov [rsp+0x0],rax` and then `mov rax,[rsp+0x0]`.

- **The distance is as short as it can be**: none for the first slot, one byte below 128, four from there, and the frame is taken and given back with one byte below 128. The first pass of a function writes other lengths than the second, which does not matter, since its code is thrown away and every jump is filled in by the pass that wrote it.
- **A slot just stored is not loaded again.** The store notes its slot, its register and the place in the output behind it; a load of that slot at that place is a copy of the register, or nothing. The note holds only while no byte is written, and is dropped where control may arrive from elsewhere with none written: a label, the end of a block, the ends of a body compiled in place.
- **A function that needs a slot is called, not compiled in place.** `sortsift` was [7% slower in place](#calls-compiled-in-place-2026-11-20), for want of registers, and stayed out only because its code was 165 bytes; with short distances it is under the limit of 160. The rule is now what was meant: a body that needs more than six places at once is not put into its caller.

The compiler is 48210 bytes where it was 49042, and the six algorithm programs each lost about a seventh of their bytes, most of it in the harness. Kernel times over those of C, the geometric mean of each program's rows, one build each:

| program | before | after |
|---|---:|---:|
| hashtab | 1.171 | 1.160 |
| heapsort | 1.195 | 1.128 |
| quicksort | 1.214 | 1.220 |
| mergesort | 1.040 | 0.995 |
| graph | 1.320 | 1.324 |
| bsearch | 0.941 | 1.109 |
| isort (elapsed) | 0.994 | 0.990 |

`heapsort` and `mergesort`, whose kernels have variables in slots, gain 6 and 4%. The loop of `bsearch` that takes its time, the body of `searchlower` in place, has every variable in a register and is the same instructions at another address, now across the end of a line of 64 bytes (0x16ee to 0x171b): it is the program whose time [moves most with the place of its code](#the-algorithm-kernels-in-a-library-2026-11-21), and one build each is no comparison for it.

## collatz within its statements, 2026-11-21

`collatz` was [12.6% behind C](#against-the-criteria-of-the-redesign-2026-11-20), and three things were named that would close it, all across statements. Two parts of the gap are inside one statement and were taken first. `if x%2` computed the remainder, a copy and an `and`, to test it; it is now `test r12b, 1`. `x=x*3+1` was `imul`, `add` and a copy back; it is `lea r12, [r12+r12*2+1]`, and so is any variable in a register times 3, 5 or 9 with a number added or taken away. The inner loop went from ten instructions to eight.

The time moves more with the place of the code than with the change, so both compilers built the program with 1, 5, 9, ... 29 bytes of data before it, and `perf` timed each, AIL over C:

| bytes before | 1 | 5 | 9 | 13 | 17 | 21 | 25 | 29 | median |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| before | 1.086 | 1.139 | 1.218 | 1.219 | 1.153 | 1.103 | 1.075 | 1.079 | 1.121 |
| after | 1.055 | 1.057 | 1.126 | 1.086 | 1.209 | 1.196 | 1.153 | 1.062 | 1.106 |

The best place is 5.5% behind C where it was 7.5%, the median 10.6% where it was 12.1%, and the same code is anywhere from 5.5 to 21% behind by where it stands, which the two instructions saved do not touch.

What is left is not done: C adds one to the count twice and halves at once after an odd step, since 3x+1 is even, which folds the two branches of the `if` into one round. That needs the `if`, its `else` and what they assign seen together, and the compiler writes the code of a line when it has read it. A loop-invariant address in a register, the third thing named, does not occur in this loop. The item is closed at this figure.

## The place of a loop, 2026-11-21

The time of a short loop moved with the bytes that happened to stand before it: `heapsort` at a thousand elements by a fifth, `bsearch` by more than a third, and `isort` and `collatz` each had a place where they lost a fifth. `perf` measured the one place `make` gave a program, and the compiler aligned nothing, while `gcc -O3` aligns its loops. So one build each compared two compilers badly, as [the row of `bsearch`](#stack-slots-2026-11-21) above shows.

**`perf --places`** asks the question itself: it compiles a program eight times with `build/ailc`, with 1, 5, 9, ... 29 bytes of `data` before its first line, times each as it times `build/<name>`, and prints AIL / C of each, for an algorithm program the geometric mean over its kernel times, with the least, the median, the most, and how far the most is above the least.

**The first line of a loop now starts at a multiple of 64 in the file**, a line of the cache: the line behind a `while`, and in a function a label that a jump goes back to. The bytes before it are instructions that do nothing, run once when the loop is entered. A label of the top level cannot be padded, since a jump back to it is not known when it is read, and a label in a body compiled in place is not. Multiples of 16 and of 32 were tried first on `bsearch` and `collatz`: 16 left `collatz` with two times a seventh apart, 32 one place in eight that far off, 64 none. Padding the entry of each function as well changed nothing on `heapsort`, `bsearch` and `collatz` and is not done.

AIL / C over the eight places, before (the compiler of the section above, by the same method in a script) and after (`perf --places`):

| program | least | median | most | most above least | least | median | most | most above least |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| | before | | | | after | | | |
| collatz | 1.053 | 1.104 | 1.210 | 14.9% | 1.123 | 1.124 | 1.126 | 0.3% |
| sieve | 0.996 | 1.007 | 1.034 | 3.8% | 0.965 | 0.971 | 0.983 | 1.9% |
| isort | 0.993 | 0.995 | 1.215 | 22.4% | 0.992 | 0.994 | 0.995 | 0.3% |
| bsearch | 0.802 | 0.913 | 1.106 | 37.9% | 0.881 | 0.886 | 0.888 | 0.8% |
| heapsort | 1.046 | 1.129 | 1.263 | 20.7% | 1.037 | 1.044 | 1.065 | 2.6% |
| quicksort | 1.183 | 1.204 | 1.231 | 4.1% | 1.210 | 1.212 | 1.221 | 0.9% |
| mergesort | 0.940 | 1.010 | 1.055 | 12.2% | 0.964 | 0.967 | 0.969 | 0.5% |
| hashtab | 1.119 | 1.132 | 1.160 | 3.7% | 1.114 | 1.119 | 1.126 | 1.1% |

- **The place no longer decides.** The most is 0.3 to 2.6% above the least where it was 3.7 to 37.9%, so a figure of one build now says something.
- **The median is better for six of the eight**, by 8% for `heapsort`, 4% for `mergesort` and `sieve`, 3% for `bsearch`: an unaligned loop was more often badly placed than well.
- **`collatz` and `quicksort` are steady at a worse median**, 1.124 for 1.104 and 1.212 for 1.204. `collatz` had places where it was 5% behind C; its loop has an `if` and an `else` in it, and where the jumps inside the loop fall counts as well as where the loop starts, which a start at a multiple of 64 fixes at one arrangement and not the best one. `graph`, not measured before, is 1.238 to 1.250.
- **The cost is bytes**: the compiler is 51175 where it was 48970, `heapsort` 8597 for 6978. The limit of a body compiled in place leaves the padding out, so which functions are compiled in place is as before.

## Bodies that call and name globals, 2026-11-21

A function was compiled in place of its calls only if its body called nothing and named no `buf`, `data` or `var`, because each such line adds an entry to a table that is sized for a source read once. The tables now have room of their own for such bodies, behind the room the source limit gives the lines of the source: a function's entry says how many entries its body adds to each, and a call is compiled in place only while that room holds them. `tests/inline_fills_tables` has 600000 calls of a body with four calls and eight uses of a buffer, and `tests/bytes.sh` computes the whole file of 600000 calls of a body with four calls: the compiler puts 262144 bodies in place, a million entries, and calls the function from there on. (The first form of this checked only that the body at hand fitted, so the lines read after it could pass the end of the table; the review of the pull request found it.)

The compiler is the program with the most such functions. Its source, built by the compiler from before and by itself, compiles the compiler in 7.13 and 6.68 ms (the median of nine times ten runs on one processor), 6% less; the file is 63207 bytes for 51175. The programs of the tables above, one build each now that loops are aligned, AIL / C before and after: `heapsort` 1.058 and 1.037, `ndmatmul` 1.559 and 1.541, `ndelem` 1.478 and 1.474, `ndreduce` 1.213 and 1.210, `hashtab` 1.132 and 1.134, `graph` 1.238 and 1.233, `bsearch` 0.883 and 0.883. Their kernels were in place already or call nothing; what moved is within a run or two.

**Bodies in bodies.** A function that had a call compiled in place was itself always called, so one level was all. Such a function is now fit like any other, and bodies are read in bodies, four deep. No program of the tables changed by a byte: their kernels were one level. The tools are deeper. Built by the compiler before and after, on one processor, the median of seven runs: `build/tools/tokens` over the examples 18.8 and 18.2 ms, `infix` on `compiler/expr.aw` 5.6 and 5.4 ms, `randvars dump` of 300 programs 27.0 and 24.9 ms, 3, 4 and 8% less. The compiler compiles itself in the same time, 7.12 and 7.17 ms. The price is bytes again: `perf` is 50086 for 37862, `par` 36067 for 26244, the compiler 66343 for 63207, and the six async programs are each a little smaller.

What stays a call, and why, is in [compiler.md](https://github.com/CristianosLeite/ail/blob/main/docs/compiler.md), under "A call compiled in place": a call at the top level, which is compiled in one pass with a register for each of its first six variables and no place used twice, so a body's variables would take the program's registers; a function with more variables at once than registers; and the fifth body in four.

## Registers in an async function, 2026-11-21

Every variable of an async function lived in the record of its task, since a task that waits goes on in a later poll, where the registers hold something else. That is only needed for a variable in use across an `await`. The compiler now notes the awaits of a function in its first pass and keeps in the record what has one in its range, and the parameters; a variable computed and read between two awaits has a register, one of the five behind `rbx`, which holds the record. The poll function saves those it uses when it is entered and gives them back wherever it returns.

`bench/async/calc` is a task that waits and between two waits runs a loop of arithmetic on three such variables, with its sum across the waits. A hundred tasks, a hundred million rounds in all, on one processor, the compiler before and after, three runs each:

| rounds between two waits | before | after |
|---:|---:|---:|
| 1000 | 1.41 ns a round | 1.11 |
| 10 | 2.70 | 2.33 |

A loop between waits is a fifth faster, and still a seventh with a wait every ten rounds. `switch`, a task that only waits and whose one variable is in use across every wait, takes 199 ms for ten million waits where it took 198: a function without such variables saves `rbx` and nothing else, as before. The other programs of [the async section](#async-2026-11-20) have their variables in loops with awaits and are the same code but for that.

## Collections, 2026-11-21

`lib/std/vec.aw`, `queue.aw`, `heap.aw` and `map.aw` had tests and no time. Two programs now time them over the harness, with the same in C and in Rust: `collseq` pushes every key to a vector and pops them, sends them through a queue with room for 1024, and pushes them to a heap and takes them out in order; `collmap` puts every key into a map with its index, gets each, and deletes each, three kernels on one map. A kernel makes its collection and gives it back inside the clock. In C the collections are written by hand in the program, arrays from `malloc` and `realloc` and a table with the layout, the hash and the growth of `lib/std/map.aw`; in Rust they are `Vec`, `VecDeque`, `BinaryHeap` and `HashMap` of the standard library. `tests/algo.sh` requires of the three what awk and `sort(1)` make of the keys. Median time of the kernel in milliseconds (first and third quartile):

| program, input, count x runs | AIL | C | Rust | AIL / C |
|---|---:|---:|---:|---:|
| collseq, random, 1000 x 2000 | 58.64 (58.05, 59.16) | 23.01 (22.64, 23.22) | 39.28 (39.04, 39.51) | 2.548 slow |
| collseq, few, 1000 x 2000 | 57.58 (57.45, 57.92) | 26.97 (26.60, 27.06) | 39.31 (39.18, 39.40) | 2.135 slow |
| collseq, random, 1000000 x 2 | 227.93 (227.06, 229.17) | 205.20 (204.24, 206.28) | 173.35 (171.62, 175.40) | 1.111 slow |
| collmap, random, 1000 x 2000 | 129.30 (128.85, 130.63) | 22.73 (21.97, 23.44) | 75.96 (75.65, 76.11) | 5.689 slow |
| collmap, few, 1000 x 5000 | 77.52 (77.32, 77.75) | 29.39 (28.84, 30.00) | 98.52 (98.26, 98.82) | 2.638 slow |
| collmap, random, 1000000 x 2 | 266.49 (265.10, 267.37) | 226.32 (225.70, 226.82) | 263.44 (261.16, 265.83) | 1.178 slow |
| collmap, few, 1000000 x 4 | 81.85 (81.48, 82.25) | 56.64 (56.26, 57.12) | 86.78 (86.34, 87.68) | 1.445 slow |

Geometric mean of AIL / C over 7 inputs: 2.054

Algorithms, median time of the kernel in milliseconds (first and third quartile)

| program, input, count x runs | AIL | C | Rust | AIL / C |
|---|---:|---:|---:|---:|
| collseq vec, random, 1000 x 2000 | 14.27 (14.16, 14.48) | 0.87 (0.87, 0.88) | 1.36 (1.35, 1.37) | 16.358 slow |
| collseq queue, random, 1000 x 2000 | 9.74 (9.54, 10.02) | 0.96 (0.96, 0.98) | 3.37 (3.35, 3.39) | 10.109 slow |
| collseq heap, random, 1000 x 2000 | 34.26 (33.92, 34.60) | 20.74 (20.36, 20.98) | 33.98 (33.76, 34.11) | 1.652 slow |
| collseq vec, few, 1000 x 2000 | 14.30 (14.17, 14.70) | 0.88 (0.87, 0.88) | 1.36 (1.35, 1.37) | 16.309 slow |
| collseq queue, few, 1000 x 2000 | 9.81 (9.63, 10.03) | 0.97 (0.96, 0.97) | 3.37 (3.35, 3.39) | 10.130 slow |
| collseq heap, few, 1000 x 2000 | 33.19 (32.97, 33.40) | 24.71 (24.30, 24.77) | 33.99 (33.90, 34.08) | 1.343 slow |
| collseq vec, random, 1000000 x 2 | 11.97 (11.89, 12.34) | 5.38 (5.32, 5.45) | 5.84 (5.83, 5.93) | 2.227 slow |
| collseq queue, random, 1000000 x 2 | 5.88 (5.80, 5.95) | 2.93 (2.89, 2.98) | 4.14 (4.09, 4.21) | 2.005 slow |
| collseq heap, random, 1000000 x 2 | 203.05 (202.05, 203.49) | 190.18 (189.34, 191.28) | 156.37 (154.50, 158.13) | 1.068 |
| collmap put, random, 1000 x 2000 | 102.91 (102.61, 103.95) | 14.69 (14.11, 15.62) | 45.12 (44.85, 45.27) | 7.007 slow |
| collmap get, random, 1000 x 2000 | 10.74 (10.66, 10.80) | 3.65 (3.63, 3.68) | 12.25 (12.24, 12.30) | 2.940 slow |
| collmap del, random, 1000 x 2000 | 15.24 (15.17, 15.36) | 3.79 (3.70, 3.90) | 17.90 (17.86, 18.04) | 4.024 slow |
| collmap put, few, 1000 x 5000 | 27.98 (27.85, 28.04) | 9.98 (9.90, 10.18) | 34.88 (34.75, 35.03) | 2.802 slow |
| collmap get, few, 1000 x 5000 | 17.72 (17.65, 17.81) | 8.22 (8.12, 8.34) | 30.21 (30.15, 30.41) | 2.156 slow |
| collmap del, few, 1000 x 5000 | 31.32 (31.17, 31.56) | 10.60 (10.07, 10.73) | 32.56 (32.44, 32.71) | 2.956 slow |
| collmap put, random, 1000000 x 2 | 161.87 (161.24, 162.72) | 144.32 (143.94, 144.45) | 93.24 (92.79, 93.75) | 1.122 slow |
| collmap get, random, 1000000 x 2 | 45.21 (44.76, 45.46) | 34.99 (34.71, 35.39) | 77.94 (76.99, 79.22) | 1.292 slow |
| collmap del, random, 1000000 x 2 | 50.90 (50.40, 52.20) | 40.09 (39.58, 40.97) | 85.47 (83.24, 87.98) | 1.270 slow |
| collmap put, few, 1000000 x 4 | 20.97 (20.87, 21.10) | 12.11 (11.98, 12.20) | 26.39 (26.31, 27.08) | 1.731 slow |
| collmap get, few, 1000000 x 4 | 19.33 (19.22, 19.53) | 13.10 (12.87, 13.18) | 26.55 (26.23, 26.87) | 1.476 slow |
| collmap del, few, 1000000 x 4 | 34.10 (34.00, 34.33) | 24.83 (24.79, 25.01) | 26.75 (26.63, 27.00) | 1.373 slow |

The geometric mean of AIL / C over the 21 kernel times is 2.816, and it is two different things.

- **At a million keys the collections are 1.07 to 2.2 times C**: the heap 1.07, the map 1.12 to 1.29 on keys that differ and 1.37 to 1.73 on sixteen keys, the vector and the queue 2.0 to 2.2, 6 ns a key against 2.7 for a push and a pop. Against Rust the map is ahead on keys that differ in `get` and `del` (45 and 51 ms for 78 and 85), since `HashMap` computes SipHash, and behind in `put` (162 for 93).
- **At a thousand keys they are 10 to 16 times C for the vector and the queue, 7 times for `put`.** That is not the code of the collections: it is `alloc`, which is one `mmap` for each call, and `free`, one `munmap`. A run of `collseq` at a thousand keys makes five of each (the vector grows once, the queue has its room, the heap grows once) and a run of `collmap` six, 10000 and 12000 pairs in the 2000 runs, where the C program makes 34 system calls in all, because `malloc` hands out again what was freed (`strace -c`). A vector of a thousand keys costs 7.1 microseconds a run in AIL and 0.44 in C, and nearly all of the difference is those calls and the pages they bring in zeroed. `get` and `del`, which allocate nothing, are 2.9 and 4.0 times C at a thousand keys on keys that differ: a call of `mapget` for each key, where C has the probe in the loop.
- **What this says for a program**: a collection that lives long costs about what it costs in C; one made and dropped in a loop pays a system call each time it grows, and is better made once and used again. An allocator that keeps freed blocks would change the first table and nothing else; it is not part of the language today, where `alloc` is the system call by design.

## After the open items, 2026-11-21

`build/tools/perf`, whole, with the compiler as it is after the sections from [stack slots](#stack-slots-2026-11-21) to [collections](#collections-2026-11-21): short distances to stack slots, a mask tested where it is, `lea` for a product, loops at multiples of 64 bytes, calls in place of bodies that call and name globals and of bodies in bodies. CPU-bound, median elapsed in milliseconds (first and third quartile):

| program | AIL | C | Rust | asm | AIL / C |
|---|---:|---:|---:|---:|---:|
| compute | 134.12 (134.02, 134.78) | 134.69 (134.02, 135.09) | 134.62 (134.37, 135.07) | 134.01 (133.48, 134.57) | 0.996 |
| sieve | 50.63 (49.94, 51.04) | 52.27 (51.74, 52.51) | 53.32 (52.84, 54.01) |  | 0.969 |
| collatz | 89.15 (88.96, 89.90) | 79.31 (79.16, 79.94) | 58.81 (58.40, 59.18) |  | 1.124 slow |
| fnv | 94.31 (94.09, 94.65) | 94.77 (94.26, 95.21) | 94.91 (94.37, 95.21) |  | 0.995 |
| isort | 50.83 (50.67, 51.12) | 50.98 (50.88, 51.30) | 71.67 (71.49, 72.01) |  | 0.997 |

The geometric mean of AIL / C over the five is 1.015, where the redesign ended at 1.039 and the follow-up at 1.032. `collatz` is the one program behind, 12.4%, as [its section](#collatz-within-its-statements-2026-11-21) explains. Over the 62 kernel times of the six algorithm programs and the three array programs the geometric mean is 1.145, where it was 1.201 with [the clock of the harness](#the-clock-of-the-harness-2026-11-21); by program it is `bsearch` 0.851, `mergesort` 0.969, `heapsort` 1.048, `hashtab` 1.127, `ndreduce` 1.174, `quicksort` 1.197, `graph` 1.250, `ndelem` 1.496 and `ndmatmul` 1.535. With the 21 kernel times of the collections, which are 2.808 in the mean for the reason given above, it is 1.436 over 83.
