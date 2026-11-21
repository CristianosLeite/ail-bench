# Performance

What AIL programs cost in time against the same programs in C, Rust and assembly, and what the task pool, the async runtime and the array library carry. This page has the current figures only. Every one was measured on 2026-11-21 with the tools of this repository, against the main repository at revision `8c46174`, whose compiler, libraries and examples are those of the revision in `ail.pin` (`336dff8`); each section names its command. The measurements made while the compiler and the libraries were being written, and what each changed, are in the history of this file up to the commit `cb14f3b`.

The machine is a 12th Gen Intel Core i7-12700H (6 performance cores with two threads each and 8 efficiency cores, 20 hardware threads) with Linux 7.0; gcc 15.2.0, rustc 1.99.0, NumPy 2.3.5.

```
make
build/tools/perf              # every program
build/tools/perf compute      # some programs only
build/tools/perf --quick      # few rounds, to see that it runs
build/tools/perf --cpu 4      # another CPU than 2
build/tools/perf --qsort      # the sorts against the qsort of the C library too
build/tools/perf --places     # each program compiled at eight places
```

## Method

`tools/perf.aiw`, built as `build/tools/perf`, times every program against the C, Rust and assembly program in `bench/` that does the same.

- C is built with `gcc -O3`, Rust with `rustc -O`, assembly with `as` and `ld`; the AIL program is `build/<name>`, so run `make` first. A language without a source for a program is left out of its row.
- Every executable must give the expected output and exit status for the input before anything is timed. Where the output is a computed value, all languages must give the same bytes as C.
- A run is a child process of `lib/std/proc.aiw` and the wait for it (`fork`, `dup2`, `execve`, `wait4`), timed with the monotonic clock, with stdin from a file and stdout to `/dev/null`, the tool pinned to one CPU (2, a performance core). Each round runs every language once, in an order shuffled from a fixed seed, so that drift of the machine falls on all of them alike.
- The figure is the median, followed by the first and third quartile, which show the variation from run to run.
- The input is read at run time, so no compiler can compute the result while compiling.
- A ratio of AIL to C above 1.10 is marked `slow`.

The programs are of three kinds:

- **Startup**: `hello`, `echo`, `count`, `fact` and `loop` do almost nothing, so their time is the cost of starting a process. 25 warm-up rounds and 500 timed ones, in microseconds.
- **CPU-bound**: five programs that read their input, compute and write a 64-bit result. 2 warm-up rounds and 15 timed ones, in milliseconds.
- **Programs of the harness**: the six algorithm programs, the three array programs and the two collection programs are kernels and their call of `bench/algo/harness.aiw`. They are run on several inputs, take the time of their kernels themselves and are reported in tables of their own, [as said below](#algorithms). The same rounds as the CPU-bound programs.

| program | what it does | what it exercises |
|---|---|---|
| `compute` | a hundred million steps of `x = (x ^ (x >> 13)) * 1664525 + 1013904223` | arithmetic in one dependent chain |
| `sieve` | counts the primes below 16000000 in a table of bytes | byte stores at a stride, a nested loop |
| `collatz` | adds up the lengths of the Collatz sequences from 1 to 500000 | a branch that cannot be predicted, `% 2`, `/ 2`, `* 3` |
| `fnv` | hashes a megabyte a hundred times with FNV-1a | byte loads, a multiplication per byte |
| `isort` | sorts 30000 numbers by insertion (`sortins` of `lib/algo/`) | 64-bit loads and stores, a comparison per step |

The last four have no assembly equivalent, and neither have the programs of the harness.

The tables from here to [Collections](#collections) are one run of `build/tools/perf --qsort`.

## Startup

Median elapsed in microseconds (first and third quartile):

| program | AIL | C | Rust | asm |
|---|---:|---:|---:|---:|
| hello | 65.79 (57.49, 69.95) | 203.01 (197.10, 212.05) | 293.56 (287.10, 303.12) | 64.89 (56.75, 69.59) |
| echo | 67.86 (60.47, 72.49) | 206.37 (199.46, 214.96) | 295.52 (288.99, 304.11) | 66.62 (59.77, 71.72) |
| count | 68.96 (60.88, 73.73) | 207.66 (200.57, 216.53) | 295.87 (290.53, 305.83) | 68.88 (60.99, 72.65) |
| fact | 65.10 (57.24, 69.23) | 200.53 (194.71, 210.01) | 287.95 (282.83, 296.97) | 63.93 (56.07, 68.69) |
| loop | 68.80 (60.84, 73.48) | 203.92 (197.07, 211.94) | 289.61 (283.71, 297.38) | 67.97 (59.76, 73.17) |

An AIL program starts in a third of the time of the C one and less than a quarter of the Rust one, like the assembly program: it is a static file of a few hundred bytes with no loader and no runtime to set up.

## CPU-bound

Median elapsed in milliseconds (first and third quartile):

| program | AIL | C | Rust | asm | AIL / C |
|---|---:|---:|---:|---:|---:|
| compute | 131.14 (131.02, 131.20) | 131.24 (131.19, 131.58) | 131.42 (131.29, 131.84) | 131.37 (131.10, 131.92) | 0.999 |
| sieve | 48.17 (48.04, 48.50) | 49.72 (49.56, 50.41) | 50.89 (50.80, 51.22) |  | 0.969 |
| collatz | 87.32 (87.23, 87.37) | 77.63 (77.45, 77.81) | 57.27 (57.25, 57.47) |  | 1.125 slow |
| fnv | 92.03 (91.91, 92.28) | 92.14 (92.09, 92.39) | 92.33 (92.25, 92.63) |  | 0.999 |
| isort | 49.62 (49.59, 49.67) | 49.95 (49.90, 50.00) | 70.24 (70.19, 70.39) |  | 0.993 |

Geometric mean of AIL / C over the five: 1.016. Four are level with C or ahead of it. `collatz` is the one program behind, by 12.5%: C adds one to the count twice and halves at once after an odd step, since 3x+1 is even, which folds the two branches of the `if` into one round, and the AIL compiler writes the code of a line when it has read it, so it compiles the two branches as written. `isort` is three tenths faster than the Rust program, which checks its indexes.

## Algorithms

`quicksort`, `mergesort`, `heapsort`, `bsearch`, `hashtab` and `graph` ask what the compiler makes of code that calls functions and walks arrays: three sorts, a binary search, a hash table and a graph traversal, each the same algorithm with the same arrays in AIL, C and Rust. In AIL the kernels are functions of `lib/algo/`.

A program reads five words from stdin: a seed, a count, a shape, a number of runs and a mode (`bench/algo/harness.aiw`, with `harness.h` and `harness.rs` for the other two languages). It makes its input from the seed with splitmix64, so the three languages work on the same words, and then runs its kernel that many times, each time on a fresh copy of the input. The copy, the making of the input, the clearing of a table and the checksum are outside the timed region: the monotonic clock is read just before and just after each call of the kernel, and the sum over the runs is written behind a checksum of the last result. `perf` sends that output to a file, reads it after every run, requires the checksum of C in it, and reports the time of the kernel apart from the elapsed time of the process, which also has the start of the process and the setup in it.

The three languages read the clock the same way, through `__vdso_clock_gettime`, a function the kernel maps into every process that reads the clock without entering the kernel: `clock_gettime` of the C library and `Instant::now` of Rust call it, and the harness in AIL finds it in the file the kernel maps (`algoclk`) and calls it with `callp`. A system call for each reading would be charged to AIL alone, on every run of a kernel.

The inputs are rows of a table in `tools/perf.aiw`: a thousand elements with enough runs for some tens of milliseconds, and a million with one to four runs, in up to four shapes. `random` is words of 64 bits; `sorted` and `reversed` rise and fall by steps below 65536; `few` is values below 16 for a sort, steps below 2 for `bsearch`, 128 distinct keys for `hashtab` and at most one neighbour a vertex for `graph`, where `random` is up to 15. A small array lies in the first cache and shows the instructions; a large one shows the memory as well.

Before anything is timed the whole result, not its checksum, must be the same bytes as that of C at the counts 0, 1 and 1000 in the shape of the row. That the results are right, and not only alike, is checked by `tests/algo.sh` in `make test`: the sorts against `sort(1)`, the others against awk programs, in the three languages.

The time of the kernels, median in milliseconds (first and third quartile); the column `C qsort` is `bench/qsort.c`, the sort of the C library with a comparison function, on the same input:

| program, input, count x runs | AIL | C | Rust | C qsort | AIL / C |
|---|---:|---:|---:|---:|---:|
| quicksort, random, 1000 x 1000 | 21.05 (20.93, 21.17) | 13.77 (13.22, 14.11) | 20.48 (20.40, 20.54) | 32.68 (32.45, 33.04) | 1.529 slow |
| quicksort, sorted, 1000 x 1000 | 13.66 (13.62, 13.70) | 10.88 (10.84, 10.89) | 13.90 (13.87, 13.92) | 13.96 (13.95, 14.00) | 1.256 slow |
| quicksort, reversed, 1000 x 1000 | 11.56 (11.54, 11.59) | 8.88 (8.87, 8.90) | 11.60 (11.55, 11.62) | 14.46 (14.38, 14.54) | 1.301 slow |
| quicksort, few, 1000 x 1000 | 3.76 (3.74, 3.81) | 2.71 (2.71, 2.72) | 2.79 (2.77, 2.80) | 24.82 (24.70, 25.08) | 1.385 slow |
| quicksort, random, 1000000 x 1 | 72.06 (71.81, 72.20) | 66.09 (65.83, 66.16) | 71.81 (71.77, 71.92) | 93.74 (93.67, 93.90) | 1.090 |
| quicksort, sorted, 1000000 x 1 | 346.72 (345.55, 347.71) | 342.95 (339.38, 343.57) | 358.01 (356.65, 358.60) | 25.96 (25.93, 26.08) | 1.011 |
| quicksort, reversed, 1000000 x 1 | 223.65 (220.93, 225.48) | 221.03 (220.35, 221.64) | 237.56 (235.63, 238.64) | 28.16 (28.12, 28.22) | 1.012 |
| quicksort, few, 1000000 x 4 | 48.32 (48.17, 48.58) | 45.33 (45.25, 45.50) | 48.18 (48.13, 48.43) | 235.52 (235.08, 235.99) | 1.066 |
| mergesort, random, 1000 x 1000 | 18.55 (18.31, 18.60) | 18.51 (18.27, 18.75) | 15.76 (15.65, 15.86) | 33.11 (32.96, 33.22) | 1.002 |
| mergesort, sorted, 1000 x 1000 | 4.62 (4.61, 4.65) | 5.21 (5.17, 5.28) | 5.10 (5.07, 5.13) | 13.97 (13.95, 14.01) | 0.887 |
| mergesort, reversed, 1000 x 1000 | 4.71 (4.71, 4.72) | 5.13 (5.10, 5.16) | 4.62 (4.61, 4.63) | 14.42 (14.38, 14.52) | 0.919 |
| mergesort, few, 1000 x 1000 | 9.56 (9.52, 9.64) | 8.82 (8.71, 8.97) | 8.51 (8.47, 8.54) | 24.93 (24.77, 25.34) | 1.084 |
| mergesort, random, 1000000 x 1 | 64.53 (64.45, 64.82) | 66.94 (66.51, 67.48) | 65.90 (65.54, 66.24) | 93.81 (93.57, 94.10) | 0.964 |
| mergesort, sorted, 1000000 x 1 | 9.90 (9.79, 9.98) | 10.25 (10.20, 10.41) | 10.42 (10.35, 10.52) | 25.99 (25.87, 26.55) | 0.966 |
| mergesort, reversed, 1000000 x 1 | 9.94 (9.89, 10.09) | 10.10 (10.03, 10.26) | 10.07 (10.05, 10.11) | 28.02 (27.94, 28.17) | 0.984 |
| mergesort, few, 1000000 x 1 | 29.09 (28.94, 29.36) | 30.58 (30.36, 31.43) | 29.99 (29.94, 30.43) | 59.71 (59.58, 59.84) | 0.951 |
| heapsort, random, 1000 x 1000 | 15.77 (15.33, 16.30) | 17.27 (17.14, 17.62) | 16.02 (15.88, 16.84) | 33.11 (32.76, 33.31) | 0.913 |
| heapsort, sorted, 1000 x 1000 | 16.91 (15.45, 17.37) | 13.24 (13.17, 13.32) | 20.60 (20.58, 20.61) | 13.96 (13.94, 14.01) | 1.278 slow |
| heapsort, reversed, 1000 x 1000 | 9.96 (9.84, 10.87) | 10.91 (10.80, 10.98) | 19.02 (19.01, 19.05) | 14.47 (14.37, 14.51) | 0.912 |
| heapsort, few, 1000 x 1000 | 13.09 (12.23, 14.44) | 14.91 (13.93, 15.44) | 16.27 (16.08, 16.31) | 24.89 (24.66, 25.19) | 0.878 |
| heapsort, random, 1000000 x 1 | 90.31 (90.03, 90.87) | 79.87 (78.71, 80.42) | 70.38 (69.52, 72.02) | 93.73 (93.69, 93.93) | 1.131 slow |
| heapsort, sorted, 1000000 x 1 | 37.31 (37.19, 37.38) | 35.95 (35.74, 35.98) | 30.85 (30.81, 31.11) | 26.00 (25.95, 26.11) | 1.038 |
| heapsort, reversed, 1000000 x 1 | 40.32 (40.31, 40.49) | 35.07 (35.03, 35.16) | 30.72 (30.65, 31.00) | 28.22 (28.12, 28.34) | 1.150 slow |
| heapsort, few, 1000000 x 1 | 43.32 (43.29, 43.68) | 38.75 (38.71, 38.89) | 34.45 (34.27, 34.58) | 59.84 (59.83, 60.37) | 1.118 slow |
| bsearch, random, 1000 x 2000 | 38.86 (38.25, 39.78) | 54.62 (53.90, 56.04) | 41.53 (41.35, 42.13) |  | 0.712 |
| bsearch, few, 1000 x 2000 | 37.37 (36.61, 37.98) | 52.28 (51.55, 52.83) | 39.09 (38.96, 40.12) |  | 0.715 |
| bsearch, random, 1000000 x 1 | 119.91 (119.76, 120.65) | 118.15 (117.87, 118.61) | 122.90 (122.21, 124.60) |  | 1.015 |
| bsearch, few, 1000000 x 1 | 119.80 (119.44, 120.28) | 118.19 (118.04, 119.25) | 122.91 (122.15, 124.29) |  | 1.014 |
| hashtab insert, random, 1000 x 5000 | 5.09 (5.06, 5.42) | 5.49 (5.32, 5.74) | 5.72 (5.63, 5.76) |  | 0.927 |
| hashtab hit, random, 1000 x 5000 | 5.98 (5.91, 6.20) | 8.54 (8.50, 8.65) | 7.11 (7.05, 7.21) |  | 0.700 |
| hashtab miss, random, 1000 x 5000 | 8.78 (8.76, 8.80) | 9.51 (9.43, 9.57) | 10.40 (10.33, 10.44) |  | 0.923 |
| hashtab insert, few, 1000 x 5000 | 4.12 (4.10, 4.15) | 4.17 (4.00, 4.23) | 4.79 (4.64, 4.99) |  | 0.988 |
| hashtab hit, few, 1000 x 5000 | 5.28 (5.13, 5.31) | 3.38 (3.36, 3.38) | 5.03 (5.02, 5.05) |  | 1.565 slow |
| hashtab miss, few, 1000 x 5000 | 6.01 (5.91, 6.06) | 3.46 (3.44, 3.51) | 5.18 (5.16, 5.26) |  | 1.735 slow |
| hashtab insert, random, 1000000 x 2 | 19.96 (19.83, 20.64) | 20.00 (19.92, 20.03) | 20.91 (20.79, 21.14) |  | 0.998 |
| hashtab hit, random, 1000000 x 2 | 25.35 (25.26, 26.05) | 22.57 (22.49, 22.62) | 24.73 (24.60, 25.02) |  | 1.123 slow |
| hashtab miss, random, 1000000 x 2 | 41.52 (41.32, 42.31) | 40.87 (40.10, 41.35) | 40.43 (40.17, 41.08) |  | 1.016 |
| hashtab insert, few, 1000000 x 4 | 3.98 (3.94, 4.02) | 3.36 (3.29, 3.49) | 4.05 (4.02, 4.15) |  | 1.184 slow |
| hashtab hit, few, 1000000 x 4 | 6.65 (6.47, 6.79) | 4.46 (4.45, 4.52) | 6.14 (6.11, 6.27) |  | 1.492 slow |
| hashtab miss, few, 1000000 x 4 | 30.64 (30.55, 31.47) | 26.34 (26.25, 26.98) | 29.44 (29.35, 30.13) |  | 1.163 slow |
| graph bfs, random, 1000 x 2000 | 10.97 (10.87, 11.06) | 10.77 (10.69, 11.00) | 9.85 (9.81, 9.90) |  | 1.019 |
| graph dfs, random, 1000 x 2000 | 18.44 (18.39, 18.59) | 15.18 (14.78, 15.77) | 18.66 (18.60, 18.70) |  | 1.214 slow |
| graph bfs, few, 1000 x 5000 | 11.49 (11.45, 11.53) | 8.05 (7.72, 8.16) | 9.02 (8.96, 9.05) |  | 1.428 slow |
| graph dfs, few, 1000 x 5000 | 13.78 (13.73, 13.89) | 7.33 (7.26, 7.43) | 14.45 (14.32, 14.70) |  | 1.879 slow |
| graph bfs, random, 1000000 x 1 | 66.09 (64.71, 71.69) | 56.84 (52.46, 58.73) | 71.69 (64.53, 72.83) |  | 1.163 slow |
| graph dfs, random, 1000000 x 1 | 130.69 (128.74, 145.68) | 128.11 (125.45, 132.26) | 130.74 (129.17, 131.67) |  | 1.020 |
| graph bfs, few, 1000000 x 2 | 32.40 (32.01, 32.77) | 25.66 (25.43, 25.91) | 27.21 (27.06, 27.50) |  | 1.263 slow |
| graph dfs, few, 1000000 x 2 | 30.02 (29.88, 30.17) | 25.99 (25.85, 26.26) | 30.12 (30.05, 30.26) |  | 1.155 slow |

The elapsed time of the same processes:

| program, input, count x runs | AIL | C | Rust | C qsort | AIL / C |
|---|---:|---:|---:|---:|---:|
| quicksort, random, 1000 x 1000 | 21.34 (21.22, 21.46) | 14.14 (13.59, 14.48) | 20.91 (20.84, 20.98) | 33.08 (32.83, 33.41) | 1.509 slow |
| quicksort, sorted, 1000 x 1000 | 13.98 (13.96, 14.04) | 11.24 (11.20, 11.27) | 14.33 (14.30, 14.37) | 14.33 (14.32, 14.36) | 1.243 slow |
| quicksort, reversed, 1000 x 1000 | 11.90 (11.88, 11.93) | 9.25 (9.23, 9.26) | 12.02 (11.97, 12.06) | 14.81 (14.74, 14.91) | 1.286 slow |
| quicksort, few, 1000 x 1000 | 4.08 (4.05, 4.11) | 3.07 (3.07, 3.09) | 3.20 (3.19, 3.22) | 25.19 (25.06, 25.46) | 1.328 slow |
| quicksort, random, 1000000 x 1 | 77.82 (77.66, 78.52) | 71.25 (71.16, 71.54) | 78.02 (77.80, 78.44) | 98.96 (98.84, 99.09) | 1.092 |
| quicksort, sorted, 1000000 x 1 | 353.66 (352.56, 354.30) | 348.84 (345.66, 349.80) | 364.30 (362.90, 365.02) | 31.94 (31.79, 32.15) | 1.014 |
| quicksort, reversed, 1000000 x 1 | 230.22 (227.50, 231.73) | 227.05 (225.85, 227.71) | 244.03 (243.07, 244.97) | 34.26 (34.15, 34.32) | 1.014 |
| quicksort, few, 1000000 x 4 | 55.30 (54.88, 55.88) | 51.59 (51.27, 51.96) | 55.65 (55.02, 55.79) | 242.30 (241.90, 243.34) | 1.072 |
| mergesort, random, 1000 x 1000 | 18.83 (18.60, 18.88) | 18.87 (18.69, 19.12) | 16.19 (16.11, 16.29) | 33.48 (33.32, 33.60) | 0.998 |
| mergesort, sorted, 1000 x 1000 | 4.91 (4.89, 4.95) | 5.59 (5.53, 5.65) | 5.52 (5.49, 5.55) | 14.33 (14.31, 14.38) | 0.879 |
| mergesort, reversed, 1000 x 1000 | 5.00 (4.99, 5.00) | 5.50 (5.45, 5.52) | 5.04 (5.02, 5.05) | 14.77 (14.73, 14.90) | 0.908 |
| mergesort, few, 1000 x 1000 | 9.87 (9.83, 9.95) | 9.20 (9.08, 9.32) | 8.93 (8.91, 8.97) | 25.32 (25.13, 25.73) | 1.073 |
| mergesort, random, 1000000 x 1 | 71.01 (70.67, 71.31) | 72.93 (72.49, 73.25) | 72.23 (71.87, 72.92) | 99.38 (98.81, 100.15) | 0.974 |
| mergesort, sorted, 1000000 x 1 | 16.08 (15.98, 16.37) | 16.07 (15.77, 16.39) | 16.45 (16.34, 16.80) | 31.64 (31.49, 32.11) | 1.001 |
| mergesort, reversed, 1000000 x 1 | 16.23 (16.17, 16.54) | 15.84 (15.64, 16.41) | 16.07 (16.01, 16.23) | 33.60 (33.32, 33.82) | 1.025 |
| mergesort, few, 1000000 x 1 | 35.68 (35.09, 36.13) | 36.42 (36.09, 37.92) | 36.51 (36.42, 37.13) | 65.02 (64.85, 65.20) | 0.980 |
| heapsort, random, 1000 x 1000 | 16.07 (15.63, 16.60) | 17.63 (17.53, 17.99) | 16.47 (16.33, 17.29) | 33.46 (33.14, 33.71) | 0.912 |
| heapsort, sorted, 1000 x 1000 | 17.27 (15.74, 17.67) | 13.61 (13.52, 13.68) | 21.04 (21.02, 21.07) | 14.35 (14.32, 14.39) | 1.269 slow |
| heapsort, reversed, 1000 x 1000 | 10.25 (10.14, 11.16) | 11.28 (11.19, 11.34) | 19.47 (19.45, 19.50) | 14.84 (14.75, 14.89) | 0.909 |
| heapsort, few, 1000 x 1000 | 13.37 (12.53, 14.72) | 15.29 (14.30, 15.82) | 16.72 (16.53, 16.74) | 25.24 (25.05, 25.56) | 0.875 |
| heapsort, random, 1000000 x 1 | 96.29 (96.04, 97.64) | 85.21 (83.95, 85.79) | 76.35 (75.47, 77.97) | 99.11 (98.87, 99.60) | 1.130 slow |
| heapsort, sorted, 1000000 x 1 | 43.08 (42.99, 43.29) | 41.21 (40.92, 41.74) | 36.56 (36.39, 36.66) | 31.26 (31.16, 31.38) | 1.045 |
| heapsort, reversed, 1000000 x 1 | 46.23 (46.07, 46.50) | 40.41 (40.16, 40.61) | 36.45 (36.23, 36.65) | 33.48 (33.42, 33.76) | 1.144 slow |
| heapsort, few, 1000000 x 1 | 49.27 (49.09, 49.88) | 43.97 (43.86, 44.19) | 40.69 (40.19, 41.12) | 65.00 (64.93, 65.94) | 1.121 slow |
| bsearch, random, 1000 x 2000 | 39.05 (38.42, 39.92) | 54.98 (54.23, 56.37) | 41.96 (41.77, 42.63) |  | 0.710 |
| bsearch, few, 1000 x 2000 | 37.52 (36.79, 38.14) | 52.58 (51.87, 53.15) | 39.53 (39.36, 40.54) |  | 0.714 |
| bsearch, random, 1000000 x 1 | 128.35 (128.02, 129.18) | 126.33 (125.92, 126.83) | 131.15 (130.71, 133.90) |  | 1.016 |
| bsearch, few, 1000000 x 1 | 128.34 (128.03, 128.92) | 126.57 (126.03, 128.69) | 131.13 (130.58, 132.43) |  | 1.014 |
| hashtab, random, 1000 x 5000 | 24.83 (24.58, 25.25) | 24.72 (24.36, 24.92) | 24.38 (24.33, 24.58) |  | 1.005 |
| hashtab, few, 1000 x 5000 | 20.27 (19.99, 20.42) | 12.04 (11.92, 12.13) | 16.24 (16.12, 16.39) |  | 1.683 slow |
| hashtab, random, 1000000 x 2 | 105.10 (105.01, 108.47) | 102.49 (101.47, 102.92) | 105.68 (105.10, 106.59) |  | 1.025 |
| hashtab, few, 1000000 x 4 | 63.32 (63.18, 64.52) | 54.69 (54.55, 56.17) | 60.88 (60.78, 62.24) |  | 1.158 slow |
| graph, random, 1000 x 2000 | 30.24 (30.11, 30.49) | 26.31 (25.88, 27.22) | 29.01 (28.97, 29.10) |  | 1.150 slow |
| graph, few, 1000 x 5000 | 27.15 (27.03, 27.32) | 15.88 (15.69, 16.09) | 24.05 (23.91, 24.22) |  | 1.710 slow |
| graph, random, 1000000 x 1 | 239.91 (232.45, 251.41) | 221.51 (216.14, 226.98) | 260.61 (252.97, 262.61) |  | 1.083 |
| graph, few, 1000000 x 2 | 74.11 (73.85, 74.90) | 62.55 (62.23, 63.04) | 71.94 (71.60, 72.50) |  | 1.185 slow |

What the figures say:

- **Most rows are within a tenth of C.** `mergesort` is level with C or ahead of it in seven of its eight rows, `bsearch` takes 0.71 of the time of C at a thousand elements and is level at a million, and `hashtab` on random keys is between 0.70 and 1.12.
- **`quicksort` at a thousand elements is the furthest of the sorts**, 1.26 to 1.53 times C, and within 9% at a million, where a part of the time is the memory, which costs every language the same.
- **`heapsort` at a million elements is 4 to 15% behind**; at a thousand it is ahead of C in three rows and 28% behind on input in order, with quartiles a tenth apart, the widest of the table.
- **Few distinct values are where `hashtab` and `graph` lose most**: the lookups of a thousand keys of few values take 1.57 and 1.74 times as long as in C, the traversals of a graph with at most one neighbour a vertex 1.43 and 1.88 times at a thousand vertices.
- `quicksort` on a million words in order takes three to five times as long as on random ones, in the three languages alike. The likely cause is the pass that keeps equal words in the middle: it brings the words from the end to the front one by one, which leaves the larger side of an ordered input out of order with its smallest word last, where the median of three picks a poor pivot. The `qsort` of the C library, a merge sort, is 13 times faster there than the C quicksort, and 5 times slower on few values, where the three-way pass has little to do.
- Against the library's `qsort`, which a C program gets without writing a sort, the three AIL kernels are faster on random input at both sizes. On input in order `mergesort` is faster at both sizes, `quicksort` level or ahead at a thousand and far behind at a million, and `heapsort` behind but for input in falling order at a thousand.

What the programs cost in tokens is in [tokens.md](tokens.md).

## Numerical arrays

`lib/nd/` is arrays of numbers on the model of NumPy ([the nd library](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#the-nd-library)). Three programs time its kernels by the method of the algorithm programs: `ndelem` adds and multiplies two arrays element by element and multiplies one by a number, `ndreduce` sums a square of numbers, sums its columns and takes a dot product, `ndmatmul` multiplies two square matrices. In AIL a kernel makes arrays of the memory the harness gives it (`ndwrap`) and calls the library, so what is timed is the library as a program uses it; in C and Rust the kernel is a plain loop over the same doubles in the same order.

The harness gives every language the same doubles (`algonum`: the lowest 20 bits of a word of splitmix64, divided by 1024, less 512), and the kernels add in the same order, so the result is the same bytes in the three, which `perf` requires before it times anything. That the results are right is checked apart: `tests/algo.sh` computes them in awk. The counts are a thousand and a million numbers for `ndelem` and sides of 100 and 1000 for the two others.

The time of the kernels, median in milliseconds (first and third quartile):

| program, input, count x runs | AIL | C | Rust | AIL / C |
|---|---:|---:|---:|---:|
| ndelem add, random, 1000 x 20000 | 5.37 (5.35, 5.38) | 2.58 (2.57, 2.62) | 2.59 (2.55, 2.67) | 2.079 slow |
| ndelem mul, random, 1000 x 20000 | 5.38 (5.37, 5.38) | 2.57 (2.55, 2.58) | 2.46 (2.42, 2.52) | 2.094 slow |
| ndelem scale, random, 1000 x 20000 | 4.93 (4.92, 4.95) | 2.31 (2.30, 2.32) | 1.90 (1.87, 1.92) | 2.133 slow |
| ndelem add, random, 1000000 x 20 | 12.45 (12.24, 12.65) | 11.60 (11.17, 12.47) | 11.68 (11.54, 11.81) | 1.073 |
| ndelem mul, random, 1000000 x 20 | 13.31 (13.08, 13.67) | 12.32 (12.17, 13.04) | 12.61 (12.52, 12.82) | 1.081 |
| ndelem scale, random, 1000000 x 20 | 11.31 (11.11, 11.57) | 11.30 (10.66, 11.50) | 10.69 (10.52, 11.10) | 1.002 |
| ndreduce sum, random, 100 x 1000 | 4.43 (4.43, 4.44) | 4.39 (4.39, 4.40) | 4.39 (4.38, 4.39) | 1.009 |
| ndreduce cols, random, 100 x 1000 | 2.78 (2.73, 2.86) | 1.32 (1.32, 1.33) | 1.41 (1.40, 1.41) | 2.103 slow |
| ndreduce dot, random, 100 x 1000 | 4.42 (4.41, 4.43) | 4.39 (4.39, 4.39) | 4.40 (4.39, 4.40) | 1.008 |
| ndreduce sum, random, 1000 x 20 | 8.87 (8.83, 8.99) | 8.86 (8.81, 9.01) | 8.92 (8.88, 9.01) | 1.002 |
| ndreduce cols, random, 1000 x 20 | 4.71 (4.65, 4.76) | 3.24 (3.20, 3.31) | 3.39 (3.36, 3.46) | 1.451 slow |
| ndreduce dot, random, 1000 x 20 | 9.05 (8.98, 9.22) | 9.00 (8.94, 9.11) | 9.48 (9.45, 9.73) | 1.006 |
| ndmatmul, random, 100 x 20 | 5.38 (5.36, 5.40) | 3.13 (3.12, 3.14) | 3.31 (3.29, 3.34) | 1.717 slow |
| ndmatmul, random, 1000 x 1 | 240.43 (238.71, 240.88) | 184.47 (173.50, 185.46) | 174.21 (172.79, 181.30) | 1.303 slow |

The elapsed time of the same processes:

| program, input, count x runs | AIL | C | Rust | AIL / C |
|---|---:|---:|---:|---:|
| ndelem, random, 1000 x 20000 | 16.53 (16.50, 16.56) | 8.46 (8.45, 8.52) | 8.24 (8.10, 8.35) | 1.953 slow |
| ndelem, random, 1000000 x 20 | 59.36 (58.56, 59.61) | 56.35 (56.16, 58.40) | 62.03 (61.45, 62.52) | 1.053 |
| ndreduce, random, 100 x 1000 | 12.65 (12.60, 12.72) | 11.23 (11.22, 11.24) | 11.46 (11.45, 11.47) | 1.126 slow |
| ndreduce, random, 1000 x 20 | 46.73 (46.62, 47.66) | 44.97 (44.68, 45.89) | 50.65 (50.41, 51.07) | 1.039 |
| ndmatmul, random, 100 x 20 | 6.35 (6.31, 6.37) | 4.19 (4.14, 4.23) | 4.48 (4.44, 4.57) | 1.516 slow |
| ndmatmul, random, 1000 x 1 | 261.43 (260.01, 262.20) | 200.82 (189.18, 201.64) | 194.68 (193.69, 202.93) | 1.302 slow |

NumPy on the same operations and sizes (`build/tools/nptime`: one thread, a result that is there before the clock starts, the median of 15 rounds; its numbers are not those of the harness, so only the time is compared), beside the medians of the kernel times above, in milliseconds:

| kernel, count x runs | AIL | C | NumPy |
|---|---:|---:|---:|
| add, 1000 x 20000 | 5.37 | 2.58 | 8.31 |
| mul, 1000 x 20000 | 5.38 | 2.57 | 8.31 |
| scale, 1000 x 20000 | 4.93 | 2.31 | 10.99 |
| add, 1000000 x 20 | 12.45 | 11.60 | 9.14 |
| mul, 1000000 x 20 | 13.31 | 12.32 | 8.99 |
| scale, 1000000 x 20 | 11.31 | 11.30 | 5.47 |
| sum, 100 x 1000 | 4.43 | 4.39 | 2.09 |
| cols, 100 x 1000 | 2.78 | 1.32 | 2.67 |
| dot, 100 x 1000 | 4.42 | 4.39 | 4.82 |
| sum, 1000 x 20 | 8.87 | 8.86 | 3.12 |
| cols, 1000 x 20 | 4.71 | 3.24 | 2.91 |
| dot, 1000 x 20 | 9.05 | 9.00 | 8.77 |
| matmul, 100 x 20 | 5.38 | 3.13 | 3.09 |
| matmul, 1000 x 1 | 240.43 | 184.47 | 179.06 |

What the figures say:

- **Sums and dot products take the time of the loops of C**, within 1% at both sides. Their elements are added to one sum in the order of the indices, as the loop of C adds them, with the sum kept in a floating-point register (`fsum` and `fdot`).
- **Where the memory is the limit the library is level with C**: arithmetic on a million numbers takes 1.00 to 1.08 times as long.
- **On a thousand numbers the arithmetic takes twice as long as in C**, 268 ns a call of `add` against 129 ns. A call wraps its three arrays (`ndwrap`) and checks them before the first element, inside the clock, which a loop of C does not.
- **The sums of columns** are 2.1 times C at a side of 100 and 1.45 at 1000, and **the product of matrices** 1.72 and 1.30.
- **NumPy** is faster than the loops of C wherever it adds in more than one place at once, sums above all (0.2 ns an element), and no faster in the product of matrices at a side of 1000 on one thread. A call of `add` on a thousand numbers takes 416 ns in NumPy, where a call costs more than the work: the library is ahead of NumPy on the arithmetic of a thousand numbers and behind it on a million, on the sums of columns and on the products.

### Tasks in the product

`ndmatmulp` of `lib/nd/par.aiw` cuts the rows of the result into runs and makes a task of each ([tasks](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#tasks)); no two write the same element, and the result is the same bits as that of `ndmatmul`. `bench/nd/mmtasks.aiw` times both in one process for a shape, a number of threads and a number of tasks, each result compared with that of `ndmatmul` outside the clocks; `bench/nd/mmtasks.sh` runs it for the table. A cell is the first call of `ndmatmulp`, which also starts the threads of the pool, and then the fastest of the later calls, which find them there, in microseconds, the median of five processes. The row `ndmatmul` is the fastest call on one thread: the least and the most of the nine medians of the shape, one for each row below it. A shape is the rows and columns of the first matrix and the columns of the second, so the first number is the rows of the result, which are what the tasks share out:

| threads, tasks | 30 x 30 x 30 | 100 x 100 x 100 | 300 x 300 x 300 | 1000 x 1000 x 1000 | 4000 x 16 x 16 | 16 x 250 x 250 | 250 x 250 x 16 |
|---|---:|---:|---:|---:|---:|---:|---:|
| `ndmatmul` | 13 to 15 | 276 to 321 | 6645 to 7291 | 223864 to 246512 | 678 to 715 | 227 to 260 | 629 to 668 |
| 1, 1 | 13, 13 | 273, 265 | 7544, 7533 | 234471, 233867 | 702, 684 | 237, 227 | 653, 641 |
| 2, 2 | 51, 10 | 417, 140 | 5609, 3767 | 125248, 124343 | 664, 366 | 320, 126 | 578, 325 |
| 4, 4 | 85, 10 | 303, 131 | 3033, 1924 | 75403, 73919 | 491, 255 | 267, 110 | 376, 220 |
| 8, 8 | 118, 8 | 236, 75 | 1688, 1488 | 50332, 52225 | 350, 152 | 176, 61 | 316, 118 |
| 14, 14 | 112, 9 | 228, 59 | 1419, 1168 | 37365, 39758 | 337, 122 | 207, 64 | 295, 90 |
| 20, 20 | 125, 13 | 218, 48 | 1101, 883 | 29432, 36784 | 330, 104 | 198, 57 | 313, 74 |
| 4, 16 | 82, 10 | 231, 105 | 2798, 2241 | 75246, 75817 | 416, 255 | 217, 122 | 371, 209 |
| 8, 32 | 115, 14 | 182, 67 | 1478, 1266 | 48532, 48846 | 337, 152 | 210, 79 | 288, 119 |
| 20, 64 | 158, 28 | 262, 48 | 1088, 879 | 31668, 36753 | 343, 105 | 195, 58 | 300, 72 |

- **With the pool there**, tasks pay from the smallest product measured: 27000 products and sums, 13 microseconds, take 8 in 8 tasks. That is where few tasks are right: 20 tasks are no faster than one thread there, and 64 take twice its time. From a million products on, as many tasks as threads is the size, 6 to 8 times faster on 20 threads.
- **The first call** starts the threads, about 40 microseconds for the first and 100 to 150 for eight or more. It is slower than one thread at a side of 30 and gains from a side of 100 on once there are eight threads: a program that multiplies once should use tasks from some hundred microseconds of work, a program that multiplies often from ten.
- **More tasks than threads** gain little: 64 tasks on 20 threads take the time of 20 from a side of 100 on, and 32 on 8 are 6 to 15% faster than 8 at the three larger squares and no faster elsewhere.
- **The shape** counts through the rows of the result, since a task is a run of rows: 16 rows of 250 are at most 16 tasks, 4 times faster, where 250 rows of 16, as many products in all, are 9 times faster. Many short rows (4000 by 16 by 16) share out as well as a square, 6.6 times.

`ndmatmulp` cuts the rows of the result into runs of the rows divided by the tasks, rounded up, and the last run is what is left. A run is a row block, computed as one piece, and `ndruns` returns their number, which `mmtasks.sh` prints beside the tasks asked for. Where the rows do not go round that is fewer blocks than tasks; with 1 to 8 tasks there is a block for each in every shape of the table, and from 14 on:

| threads, tasks asked | 30 x 30 x 30 | 100 x 100 x 100 | 300 x 300 x 300 | 1000 x 1000 x 1000 | 4000 x 16 x 16 | 16 x 250 x 250 | 250 x 250 x 16 |
|---|---:|---:|---:|---:|---:|---:|---:|
| 14, 14 | 10 | 13 | 14 | 14 | 14 | 8 | 14 |
| 20, 20 | 15 | 20 | 20 | 20 | 20 | 16 | 20 |
| 4, 16 | 15 | 15 | 16 | 16 | 16 | 16 | 16 |
| 8, 32 | 30 | 25 | 30 | 32 | 32 | 16 | 32 |
| 20, 64 | 30 | 50 | 60 | 63 | 64 | 16 | 63 |

The number is the plan and no count of tasks that ran: with one block the caller computes it and nothing is spawned, and a block whose `spawn` fails is left to the caller as well. A caller who wants a task for every thread asks for a number the rows go round with.

## Collections

Two programs time `lib/std/vec.aiw`, `queue.aiw`, `heap.aiw` and `map.aiw` over the harness, with the same in C and in Rust: `collseq` pushes every key to a vector and pops them, sends them through a queue with room for 1024, and pushes them to a heap and takes them out in order; `collmap` puts every key into a map with its index, gets each, and deletes each, three kernels on one map. A kernel makes its collection and gives it back inside the clock. In C the collections are written by hand in the program, arrays from `malloc` and `realloc` and a table with the layout, the hash and the growth of `lib/std/map.aiw`; in Rust they are `Vec`, `VecDeque`, `BinaryHeap` and `HashMap` of the standard library. `tests/algo.sh` requires of the three what awk and `sort(1)` make of the keys.

The time of the kernels, median in milliseconds (first and third quartile):

| program, input, count x runs | AIL | C | Rust | AIL / C |
|---|---:|---:|---:|---:|
| collseq vec, random, 1000 x 2000 | 13.91 (13.68, 14.34) | 0.87 (0.87, 0.90) | 1.34 (1.33, 1.35) | 15.992 slow |
| collseq queue, random, 1000 x 2000 | 9.35 (9.33, 9.65) | 0.95 (0.95, 0.95) | 3.36 (3.32, 3.39) | 9.807 slow |
| collseq heap, random, 1000 x 2000 | 34.85 (34.30, 35.58) | 20.17 (20.09, 20.47) | 33.65 (33.36, 33.70) | 1.728 slow |
| collseq vec, few, 1000 x 2000 | 13.89 (13.64, 14.03) | 0.87 (0.87, 0.87) | 1.33 (1.32, 1.34) | 16.008 slow |
| collseq queue, few, 1000 x 2000 | 9.55 (9.34, 9.67) | 0.95 (0.95, 0.96) | 3.35 (3.33, 3.37) | 10.021 slow |
| collseq heap, few, 1000 x 2000 | 33.66 (33.36, 34.03) | 24.25 (24.09, 24.39) | 33.60 (33.53, 33.70) | 1.388 slow |
| collseq vec, random, 1000000 x 2 | 11.67 (11.31, 12.19) | 5.11 (5.00, 5.35) | 6.02 (5.51, 6.13) | 2.285 slow |
| collseq queue, random, 1000000 x 2 | 5.81 (5.66, 5.93) | 2.78 (2.70, 3.10) | 4.14 (3.87, 4.23) | 2.086 slow |
| collseq heap, random, 1000000 x 2 | 198.22 (197.47, 199.49) | 185.35 (185.01, 186.07) | 151.78 (150.46, 155.56) | 1.069 |
| collmap put, random, 1000 x 2000 | 102.25 (101.70, 102.57) | 14.86 (14.11, 15.39) | 44.44 (44.20, 44.81) | 6.883 slow |
| collmap get, random, 1000 x 2000 | 10.00 (9.93, 10.02) | 3.55 (3.53, 3.56) | 12.06 (12.04, 12.10) | 2.821 slow |
| collmap del, random, 1000 x 2000 | 14.51 (14.43, 14.59) | 3.66 (3.64, 3.75) | 17.70 (17.60, 17.76) | 3.959 slow |
| collmap put, few, 1000 x 5000 | 28.31 (28.25, 28.48) | 10.10 (9.69, 10.31) | 34.13 (34.11, 34.21) | 2.805 slow |
| collmap get, few, 1000 x 5000 | 17.16 (17.11, 17.21) | 8.10 (8.00, 8.20) | 29.60 (29.56, 29.68) | 2.120 slow |
| collmap del, few, 1000 x 5000 | 29.63 (29.51, 29.87) | 10.71 (10.14, 10.98) | 31.96 (31.94, 32.00) | 2.766 slow |
| collmap put, random, 1000000 x 2 | 155.68 (153.44, 160.40) | 142.06 (137.19, 143.57) | 88.90 (88.43, 97.22) | 1.096 |
| collmap get, random, 1000000 x 2 | 44.00 (43.30, 44.75) | 34.29 (33.76, 35.59) | 72.48 (72.03, 77.41) | 1.283 slow |
| collmap del, random, 1000000 x 2 | 49.00 (48.38, 49.60) | 38.73 (38.06, 39.65) | 80.13 (76.30, 82.78) | 1.265 slow |
| collmap put, few, 1000000 x 4 | 20.44 (20.41, 20.50) | 11.62 (11.60, 11.66) | 25.50 (25.33, 26.17) | 1.759 slow |
| collmap get, few, 1000000 x 4 | 18.44 (18.39, 18.49) | 12.39 (12.37, 12.43) | 25.31 (25.17, 26.00) | 1.488 slow |
| collmap del, few, 1000000 x 4 | 32.72 (32.52, 32.79) | 23.81 (23.75, 24.05) | 25.61 (25.56, 25.73) | 1.375 slow |

The elapsed time of the same processes:

| program, input, count x runs | AIL | C | Rust | AIL / C |
|---|---:|---:|---:|---:|
| collseq, random, 1000 x 2000 | 58.93 (58.53, 59.58) | 22.98 (22.84, 23.22) | 39.42 (39.04, 39.73) | 2.564 slow |
| collseq, few, 1000 x 2000 | 57.71 (57.63, 58.45) | 27.02 (26.92, 27.54) | 39.38 (39.28, 39.45) | 2.136 slow |
| collseq, random, 1000000 x 2 | 223.87 (223.07, 225.24) | 201.06 (200.38, 201.39) | 170.53 (169.01, 173.96) | 1.113 slow |
| collmap, random, 1000 x 2000 | 127.64 (126.92, 127.98) | 23.51 (22.28, 24.10) | 75.78 (75.42, 76.02) | 5.430 slow |
| collmap, few, 1000 x 5000 | 76.04 (75.83, 77.21) | 29.90 (29.13, 30.38) | 96.90 (96.80, 97.23) | 2.543 slow |
| collmap, random, 1000000 x 2 | 257.47 (255.06, 262.35) | 223.93 (216.63, 224.99) | 250.31 (244.74, 260.98) | 1.150 slow |
| collmap, few, 1000000 x 4 | 79.77 (79.45, 79.98) | 55.08 (54.94, 55.53) | 84.28 (83.79, 85.56) | 1.448 slow |

The figures are two different things.

- **At a million keys the collections are 1.07 to 2.3 times C**: the heap 1.07, the map 1.10 to 1.28 on keys that differ and 1.38 to 1.76 on sixteen keys, the vector and the queue 2.1 to 2.3, 5.8 ns a key against 2.6 for a push and a pop. Against Rust the map is ahead on keys that differ in `get` and `del` (44 and 49 ms for 72 and 80), since `HashMap` computes SipHash, and behind in `put` (156 for 89).
- **At a thousand keys they are 10 to 16 times C for the vector and the queue, 7 times for `put`.** That is not the code of the collections: it is `alloc`, which is one `mmap` for each call, and `free`, one `munmap`, where `malloc` of the C library hands out again what was freed. A run of `collseq` at a thousand keys allocates for each of its three collections and again where one grows, and each of those is a system call and pages that come in zeroed: a vector of a thousand keys costs 7.0 microseconds a run in AIL and 0.44 in C. `get` and `del`, which allocate nothing, are 2.8 and 4.0 times C at a thousand keys that differ: a call of `mapget` for each key, where C has the probe in the loop.
- **What this says for a program**: a collection that lives long costs about what it costs in C; one made and dropped in a loop pays a system call each time it grows, and is better made once and used again. An allocator that keeps freed blocks would change the rows of a thousand keys and nothing else; it is not part of the language today, where `alloc` is the system call by design.

Over all 83 kernel times of the eleven programs of the harness the geometric mean of AIL / C is 1.433, and over their 49 elapsed times 1.201; the table of the next section has it for each program.

## The place of the code

The time of a short loop can move with the bytes that happen to stand before it, so a comparison of two compilers must not rest on one build. `build/tools/perf --places` compiles each program eight times with `build/ailc`, with 1, 5, 9, ... 29 bytes of `data` before its first line, times each as it times `build/<name>`, and prints AIL / C of each, for a program of the harness the geometric mean over its kernel times, with the least, the median, the most, and how far the most is above the least. One run of `build/tools/perf --places` with the sixteen programs named:

| program | 1 | 5 | 9 | 13 | 17 | 21 | 25 | 29 | least | median | most | most above least |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| compute | 0.998 | 0.999 | 0.999 | 0.998 | 1.000 | 0.998 | 0.998 | 0.999 | 0.998 | 0.998 | 1.000 | 0.2% |
| sieve | 0.972 | 0.967 | 0.976 | 0.974 | 0.971 | 0.981 | 0.974 | 0.974 | 0.967 | 0.974 | 0.981 | 1.4% |
| collatz | 1.124 | 1.124 | 1.125 | 1.126 | 1.126 | 1.125 | 1.125 | 1.124 | 1.124 | 1.125 | 1.126 | 0.2% |
| fnv | 0.998 | 0.996 | 0.997 | 0.998 | 0.997 | 0.997 | 0.998 | 0.997 | 0.996 | 0.997 | 0.998 | 0.2% |
| isort | 0.993 | 0.993 | 0.994 | 0.994 | 0.994 | 0.993 | 0.995 | 0.994 | 0.993 | 0.994 | 0.995 | 0.2% |
| quicksort | 1.198 | 1.202 | 1.202 | 1.197 | 1.206 | 1.229 | 1.179 | 1.190 | 1.179 | 1.200 | 1.229 | 4.3% |
| mergesort | 0.967 | 0.967 | 0.969 | 0.969 | 0.970 | 0.966 | 0.971 | 0.970 | 0.966 | 0.969 | 0.971 | 0.5% |
| heapsort | 1.056 | 1.061 | 1.049 | 1.054 | 1.049 | 1.045 | 1.052 | 1.045 | 1.045 | 1.051 | 1.061 | 1.5% |
| bsearch | 0.848 | 0.849 | 0.847 | 0.847 | 0.846 | 0.848 | 0.849 | 0.850 | 0.846 | 0.848 | 0.850 | 0.4% |
| hashtab | 1.117 | 1.119 | 1.123 | 1.132 | 1.122 | 1.111 | 1.121 | 1.112 | 1.111 | 1.120 | 1.132 | 1.8% |
| graph | 1.238 | 1.245 | 1.237 | 1.236 | 1.242 | 1.242 | 1.232 | 1.236 | 1.232 | 1.237 | 1.245 | 1.0% |
| ndelem | 1.486 | 1.481 | 1.498 | 1.495 | 1.471 | 1.501 | 1.485 | 1.491 | 1.471 | 1.488 | 1.501 | 2.0% |
| ndreduce | 1.206 | 1.206 | 1.203 | 1.205 | 1.205 | 1.205 | 1.205 | 1.204 | 1.203 | 1.205 | 1.206 | 0.2% |
| ndmatmul | 1.527 | 1.553 | 1.534 | 1.513 | 1.510 | 1.522 | 1.539 | 1.527 | 1.510 | 1.527 | 1.553 | 2.8% |
| collseq | 4.042 | 4.031 | 4.076 | 4.089 | 4.040 | 4.071 | 4.038 | 4.031 | 4.031 | 4.041 | 4.089 | 1.5% |
| collmap | 2.135 | 2.138 | 2.130 | 2.124 | 2.129 | 2.135 | 2.130 | 2.122 | 2.122 | 2.130 | 2.138 | 0.7% |

The most is 0.2 to 4.3% above the least, so a figure of one build says something: the compiler starts the first line of a loop at a multiple of 64 bytes in the file, a line of the cache. By the median the programs stand, from the fastest against C: `bsearch` 0.848, `mergesort` 0.969, `sieve` 0.974, `isort` 0.994, `fnv` 0.997, `compute` 0.998, `heapsort` 1.051, `hashtab` 1.120, `collatz` 1.125, `quicksort` 1.200, `ndreduce` 1.205, `graph` 1.237, `ndelem` 1.488, `ndmatmul` 1.527, `collmap` 2.130 and `collseq` 4.041, the last two for [the reason given above](#collections).

## Tasks

`tools/par.aiw`, built as `build/tools/par`, times the programs of `bench/par/`, each written twice: `<name>_seq.aiw` does the work in one call, `<name>_par.aiw` cuts it into tasks with `spawn` and `join` (`lib/std/task.aiw`). It says what the pool gains at 1, 2, 4, 8, 14 and 20 threads, and how small a task may be before its scheduling costs more than it saves.

```
make
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
- Beyond 6 threads a further thread is a slower core or the second thread of a core, so 20 threads cannot give 20 times.

One run of `build/tools/par`. Elapsed milliseconds with the work cut into 160 tasks, and below each row the sequential time divided by it:

| program | sequential | 1 | 2 | 4 | 8 | 14 | 20 |
|---|---:|---:|---:|---:|---:|---:|---:|
| collatz | 540.9 | 540.7 | 276.6 | 155.3 | 91.1 | 65.9 | 59.8 |
| | | 1.00 | 1.96 | 3.48 | 5.94 | 8.20 | 9.05 |
| primes | 689.9 | 691.2 | 351.9 | 189.8 | 102.7 | 66.2 | 63.8 |
| | | 1.00 | 1.96 | 3.64 | 6.72 | 10.42 | 10.81 |
| memsum | 197.7 | 190.9 | 111.6 | 75.7 | 64.1 | 60.8 | 59.9 |
| | | 1.04 | 1.77 | 2.61 | 3.08 | 3.25 | 3.30 |

- `collatz` and `primes` gain 2.0 times at 2 threads, 3.5 to 3.6 at 4, 5.9 to 6.7 at 8 and 9 to 11 at 20. The gain per thread falls after the six performance cores are taken, as the machine leads one to expect.
- `memsum` reaches 2.6 times at 4 threads and only 3.3 at 20: its limit is memory, not processors.
- At 1 thread the cost of 160 tasks is not measurable.

Elapsed milliseconds by the number of tasks the same work is cut into (`memsum` has that many in each of its 17 passes):

| program | threads | sequential | 20 | 200 | 2000 | 20000 | 200000 | 1000000 |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| collatz | 1 | 540.9 | 539.2 | 540.6 | 542.4 | 544.1 | 554.6 | 600.8 |
| collatz | 20 | 540.9 | 62.3 | 59.2 | 59.9 | 59.9 | 243.7 | 1198.5 |
| primes | 1 | 689.9 | 691.0 | 691.0 | 690.7 | 690.9 | 699.7 | 741.8 |
| primes | 20 | 689.9 | 75.8 | 63.0 | 61.1 | 62.1 | 201.9 | 1124.9 |
| memsum | 1 | 197.7 | 164.4 | 180.5 | 180.7 | 228.0 | 349.5 | 913.4 |
| memsum | 20 | 197.7 | 75.1 | 72.6 | 74.1 | 351.0 | 3394.7 | 16345.3 |

- **A task costs about 0.05 microseconds on one thread**: a million tasks add 60 ms to `collatz` and 52 ms to `primes`. That is `spawn` and `join` with no worker: a record from the free list, the queue, a lock, no system call.
- **With 20 threads a task costs about 1.2 microseconds** once tasks are so small that the threads meet at the lock: a million tasks take 1.1 to 1.2 s in both programs, about twice the sequential time, for work of 0.5 to 0.7 microseconds a task.
- **Where it turns**: tasks of about 30 microseconds and more (20000 tasks) run at full speed, 9 to 11 times. Tasks of 3 microseconds (200000) still gain 2.2 to 3.4 times. Below about 1 microsecond a task the program is slower than without tasks. `memsum` turns earlier, since its 20000 tasks a pass are 0.5 microseconds each.
- `memsum` on one thread took less time in 20 to 2000 tasks than the sequential program of this run, 164 to 181 ms against 198: its time is the memory's and moves by that much between runs, as its sequential time of 169 ms in the run below shows.
- The rule that follows: cut the work into some tens of tasks per thread, not into its smallest pieces. A hundred or a thousand tasks cost nothing and even out unequal work.

### The size of a task at each number of threads

`build/tools/par --grid --runs 3` times every pair of threads and tasks. A cell is the sequential time divided by the time with tasks, so 1 is no gain. The number of tasks is that of the whole work for `collatz` and `primes` and that of each of the 17 passes for `memsum`.

collatz, sequential 543.4 ms: that time divided by the time with tasks, median of 3 runs

| tasks | 1 | 2 | 4 | 8 | 14 | 20 |
|---:|---:|---:|---:|---:|---:|---:|
| 20 | 1.01 | 1.92 | 3.50 | 5.51 | 7.60 | 8.86 |
| 200 | 1.01 | 1.97 | 3.55 | 6.01 | 8.38 | 9.14 |
| 2000 | 1.00 | 1.94 | 3.62 | 6.02 | 8.44 | 9.31 |
| 20000 | 1.00 | 1.95 | 3.50 | 5.87 | 8.23 | 9.03 |
| 200000 | 0.98 | 1.89 | 3.19 | 4.95 | 4.19 | 2.31 |

primes, sequential 688.7 ms: that time divided by the time with tasks, median of 3 runs

| tasks | 1 | 2 | 4 | 8 | 14 | 20 |
|---:|---:|---:|---:|---:|---:|---:|
| 20 | 1.00 | 1.93 | 3.47 | 5.70 | 8.08 | 8.82 |
| 200 | 1.00 | 1.97 | 3.66 | 6.73 | 10.82 | 11.00 |
| 2000 | 1.00 | 1.97 | 3.68 | 6.86 | 11.36 | 11.47 |
| 20000 | 1.00 | 1.96 | 3.63 | 6.80 | 11.28 | 11.33 |
| 200000 | 0.98 | 1.91 | 3.44 | 5.46 | 4.47 | 3.33 |

memsum, sequential 168.8 ms: that time divided by the time with tasks, median of 3 runs

| tasks | 1 | 2 | 4 | 8 | 14 | 20 |
|---:|---:|---:|---:|---:|---:|---:|
| 20 | 0.99 | 1.53 | 2.20 | 2.44 | 2.62 | 2.80 |
| 200 | 0.96 | 1.52 | 2.22 | 2.61 | 2.77 | 2.80 |
| 2000 | 0.85 | 1.31 | 2.27 | 2.53 | 2.73 | 2.68 |
| 20000 | 0.79 | 1.26 | 1.52 | 0.58 | 0.49 | 0.48 |
| 200000 | 0.49 | 0.29 | 0.09 | 0.06 | 0.05 | 0.05 |

What a task is in time follows from the sequential time: in `collatz` 27 ms at 20 tasks, 2.7 ms at 200, 270 microseconds at 2000, 27 at 20000 and 2.7 at 200000; in `primes` a quarter more; in `memsum`, a pass being 10 ms, 500 microseconds at 20 tasks, 50 at 200, 5 at 2000, 0.5 at 20000 and 0.05 at 200000.

- **Work bound by the processor: tasks of 30 microseconds to 3 milliseconds.** From 200 to 20000 tasks the gain is the same within a tenth at every number of threads, 9.0 to 11.5 times at 20. That is ten to a thousand tasks for each thread.
- **One task for each thread is too few.** 20 tasks at 20 threads give 8.9 and 8.8 times where 2000 give 9.3 and 11.5, and at 14 threads 7.6 and 8.1 against 8.4 and 11.4: the cores are not equally fast and, in `primes`, the pieces not equally long, and with one piece each nothing evens that out.
- **Tasks of 3 microseconds are worth it up to 8 threads and not beyond.** At 200000 tasks the gain at 8 threads is 5.0 and 5.5 against 6.0 and 6.9, a fifth less; at 14 threads it falls to 4.2 and 4.5 and at 20 to 2.3 and 3.3, what 4 threads give or less, since the threads then wait for each other at the queue.
- **Work bound by memory: tasks of 5 microseconds or more, and few threads.** `memsum` gains 2.2 to 2.3 times at 4 threads and 2.7 to 2.8 at 20 with 20 to 2000 tasks a pass; more threads than the memory can feed buy little. At 0.5 microseconds a task it is slower than without tasks from 8 threads on, and at 0.05 at every number of threads.
- **A starting size**: about ten times as many tasks as threads, each of at least 100 microseconds; make them smaller only if the pieces are unequal, and not below 30 microseconds when more than 8 threads run them. Within those sizes the cost of the pool cannot be measured.

Source tokens (`o200k_base`), comments left out, as `build/tools/par` prints them:

| program | sequential | tasks | lines added |
|---|---:|---:|---:|
| collatz | 110 | 232 | 24 |
| primes | 116 | 238 | 24 |
| memsum | 154 | 280 | 22 |

The task form of a program is 122 to 126 tokens and 22 to 24 lines longer. [The token page](tokens.md#tasks-measured-on-2026-11-20) has the count of the forms.

## Async

The programs of `bench/async/` measure the runtime of `lib/async/`, which polls every task on one thread: what a wait costs, what a server on one thread carries, and what handing work to another thread costs. They are compiled and run by hand, on all processors; a figure is the median of five runs of the command beside it, unless it says otherwise.

```
make
for p in timer echod ping switch mem hop calc; do build/ailc < bench/async/$p.aiw > build/$p && chmod +x build/$p; done
gcc -O2 -o build/echod_c bench/async/echod.c
build/switch 1000 1000      # a million waits
build/mem 100000            # bytes a sleeping task
build/hop 100000            # work handed to the pool, one after the other
build/hop 100000 all        # the same, all pieces at once
build/calc 100 1000 1000    # a hundred million rounds of arithmetic between waits
build/ping 100 2000         # server and clients in one process
build/echod 0 & build/ping 100 2000 <port>
```

| program | what it does |
|---|---|
| `switch` | tasks that each `await asyncyield` many times: a task made, the asking task put aside, both polled, a record given back |
| `mem` | tasks asleep in a timer, three variables alive in each; resident memory before and after |
| `hop` | `await asyncblock` of a function that does nothing |
| `calc` | tasks that between two waits run a loop of arithmetic on variables that no wait falls into |
| `echod` | a TCP echo server, one task a connection, 4096 bytes of buffer in each task |
| `ping` | load for it: a task a connection sends 64 bytes and reads them back, and times each round trip |
| `echod.c` | the same server on `epoll` by hand in C, one thread, for comparison |

**A wait.**

| | nanoseconds |
|---|---:|
| a wait that gives the thread to another task and gets it back (`switch 1000 1000`) | 21 |
| the same with 100000 tasks, whose records no longer fit the cache (`switch 100000 10`) | 44 |
| work handed to the pool and awaited, 100000 pieces at once (`hop 100000 all`) | 1061 |
| the same one after the other, the polling thread asleep for each (`hop 100000`) | 3645 |

A wait on the thread is a few tens of instructions: a record from a free list, two entries in a queue, two indirect calls. Handing work to another thread costs 50 to 170 times that, a futex wake for the worker and, if the polling thread sleeps, an `eventfd` write and an `epoll_wait` to get back: `asyncblock` is for work of many microseconds, and the figures for `spawn` above hold for it.

**Work between waits.** A variable of an async function that no `await` falls into has a register; only what is in use across a wait lives in the record of the task. A hundred tasks of `calc`, a hundred million rounds in all: 1.09 ns a round with a thousand rounds between two waits (`calc 100 1000 1000`), 2.23 ns with ten (`calc 100 100000 10`).

**Memory.** A task asleep in a timer is 272 bytes of resident memory, in every run of `mem 100000`. A hundred thousand waiting tasks are 27 MB, and none has a stack.

**A server.** Round trips of 64 bytes over loopback, 200000 in each run, `ping` in one process and the server in another, so each has a processor; every column is the median of five runs:

| connections | server | round trips a second | mean | median | 99 of 100 within |
|---:|---|---:|---:|---:|---:|
| 1 | `echod` (AIL) | 124397 | 7 us | 7 us | 10 us |
| 1 | `echod.c` | 121826 | 7 us | 7 us | 17 us |
| 100 | `echod` (AIL) | 306481 | 324 us | 326 us | 378 us |
| 100 | `echod.c` | 297912 | 333 us | 334 us | 415 us |
| 1000 | `echod` (AIL) | 296435 | 3290 us | 3242 us | 3945 us |
| 1000 | `echod.c` | 285391 | 3419 us | 3314 us | 5450 us |

With clients and server as tasks of one thread (`ping` without a port) the same work is 172926, 176054 and 165040 round trips a second, somewhat over half, as one processor does both sides.

- **The server written with `async fn` carries what the one written on `epoll` by hand carries**, 2 to 4% more in these runs. The time of a round trip with many connections is the wait in line, connections times the service time, and the same for both.
- **Where the time goes**: for a million round trips at 100 connections (`ping 100 10000`, one run each, the times of the server from `/proc/<pid>/stat`) the AIL server uses 0.32 s in its own code and 2.92 s in the kernel; the C server 0.16 s and 2.88 s. The runtime costs 160 ns a round trip more than code written for this one program, 5% of the processor time of the server, and nine tenths of the time are the kernel's in either.
- **System calls**, counted by `strace -c` for 200000 round trips at 100 connections: the AIL server made 203580 calls of `read`, 3480 of which said "not now", 200000 of `sendto` and 85 of `epoll_wait`; the C server 200101 of `read`, 200000 of `sendto` and 3130 of `epoll_wait`. An operation asks the kernel first, so a busy server hardly ever sleeps.

**A scheduler on several threads** is not built. One thread carries as much as a server in C on one thread, its limit is the kernel's work on one processor, and the programs stay free of locks: the state of the runtime is written without a lock or an atomic instruction by the one thread that polls, and tasks share `var` cells and buffers freely. Polling tasks on several threads would put a lock or a loop around `cas` into a wait that costs 21 ns, and a task woken on another thread would pay the 1.1 to 3.6 microseconds measured above. What it would gain is the nine tenths of a server's time that are the kernel's, which do run in parallel; work that computes is already parallel, since `asyncblock` gives it to the pool. If a program needs more than one thread carries, the form to build is a thread per processor with a runtime of its own each, tasks never moving, connections spread by the kernel (`SO_REUSEPORT`); processes do that today without a change.
