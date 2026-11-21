# AGENTS.md

This file provides guidance to AI coding agents when working with code in this repository.

## What this is

The benchmarks of AIL, a proof-of-concept programming language whose design goal is low token consumption when an AI writes it (https://github.com/CristianosLeite/ail, the main repository). Here are the programs that are measured, in AIL, C, Rust and assembly, the tools that time them and count their tokens, the checks that the programs compute what they should, and the results (`docs/tokens.md`, `docs/performance.md`). The language, the compiler (`build/ailc`) and the libraries (`lib/`) are in the main repository, whose `AGENTS.md` and `docs/language.md` describe them; read those before writing AIL here.

## Rules

- Sources are indented with one tab for each block; run `build/tools/indent` on a source you write or edit, `make test` checks it.
- All repository text is in English: documentation, code comments, commit messages, test names, tool messages and the knowledge base. This holds even when the conversation is in another language.
- A commit message is `type: subject` (`feat`, `fix`, `test` or `docs`, no scope, lower case, no full stop) and a prose body wrapped at about 70 columns: the problem, what changed and why, how it was verified.
- Commit messages and pull request descriptions carry no trailers or attribution lines: no `Co-Authored-By`, no `Signed-off-by`, no "Generated with" footer. This holds even when the tool in use suggests one.
- A figure in `docs/` is one that a tool of this repository printed; say with which command, on which day, and against which revision of the main repository when that matters. Do not edit a figure by hand or carry one over from a run that was not made.
- Nothing of the main repository is copied here. What is needed from it is read through `ail/`.

## The main repository

`tools/ail.sh`, which the Makefile runs whenever make reads it, makes sure the main repository is at `ail/` (ignored by git): with `make AIL=<path>` a link to that checkout, which stays until it is removed; otherwise a clone of the address in `ail.pin` at the revision pinned there, moved to that revision when it is at another one, unless it has changes of its own. It then makes the links `lib` (to `ail/lib`) and `tools/lib/rng.aiw` (to the file there, splitmix64, which `perf` shares with a tool of the main repository). The target `build/ailc` runs `make -C ail build/ailc build/tools/indent` and links both under `build/`; a link has the time of its file, so a new compiler builds everything here again. The compiler opens a `use "path"` relative to the directory it runs in, which is why `lib` must be at the top here and everything is run from the top.

Three things of the main repository are read by name, so a change of them there is a change here: the paths under `lib/`, the ten small programs `examples/<name>.aiw` (`SMALL` in the Makefile, `progs` in `tools/perf.aiw`) and `tools/lib/rng.aiw`. When the main repository moves on, run `make AIL=<checkout> test` and the measurements that the change touches, then put the new revision in `ail.pin`; the revision must be one that the published repository has, or a clone fails.

## Commands

```
make            # fetch or link the main repository, build its compiler, every program and every tool
make AIL=../ail # the same against a checkout of your own
make test       # tests/run.sh (the tests/<name>.aiw files), tests/par.sh, tests/async.sh, tests/algo.sh,
                # tests/tools.sh, tests/tokens.sh (skipped without the pinned files), tests/indent.sh
make test T=algo_clock   # one test
tools/bench.sh  # the C, Rust and assembly programs behave like their AIL programs (needs gcc and rustc)
tools/tokens.sh # token cost of the programs in the four languages (fetches the pinned files once)
build/tools/tokens -c '#' file.aiw   # the tokens of a file without its comments; --ids file prints their numbers,
                                    # --code '#' file the text that is counted
build/tools/perf   # time the programs against bench/ built with gcc -O3 and rustc -O
build/tools/perf --qsort quicksort   # one program; the sorts also against the qsort of the C library
build/tools/perf --places heapsort   # AIL / C with the program compiled at eight places, and how far they differ
build/tools/par    # time the task programs of bench/par/ against their sequential forms at 1 to 20 threads
build/tools/par --grid --runs 3   # the gain at every number of threads with every number of tasks
bench/nd/mmtasks.sh   # the table of the matrix product with tasks
build/tools/nptime    # NumPy on the kernels of the array programs (python3 with numpy)
make clean
```

## Programs

An AIL program is `bench/<name>.aiw` if it exists to be measured, and `ail/examples/<name>.aiw` if it is one of the ten examples of the main repository; `build/<name>` is built from whichever there is, and the tools look in that order (`tools/perf.aiw` for `--places`, `tools/tokens.aiw`, `tools/bench.sh`, `tools/claudetokens.sh`). Its equivalents are `bench/<name>.c`, `.rs` and, for some, `.s`. There is no `examples/` here.

The algorithm programs `use` `bench/algo/harness.aiw` and a file of `lib/algo/` that has their kernel: five words on stdin (seed, count, shape, runs, mode); the input from the seed by splitmix64; the monotonic clock around each kernel, read through `__vdso_clock_gettime`, which `algoclk` finds in the file the kernel maps into the process, by `/proc/self/auxv`, and `algonow` calls with `callp`, as the libraries of C and Rust do, with the system call where it is not found (`tests/algo_clock`); in mode 0 a checksum of the result and the nanoseconds of each kernel, in mode 1 the input and in mode 2 the result as words. `harness.h` and `harness.rs` are the same for the programs in C and Rust. The array programs `ndelem`, `ndreduce` and `ndmatmul` call `algonum` of the harness: up to three kernels, each given two arrays of doubles, a place for as many results and the count, which with the last value 1 is the side of a square; a number is the lowest 20 bits of the word of the shape, divided by 1024, less 512, so every language has the same bits and, adding in the same order, the same result. Their kernels are calls of `lib/nd/` on arrays made with `ndwrap`, those in C and Rust plain loops. The collection programs `collseq` (a vector, a queue of 1024, a heap) and `collmap` (put, get and del on one map) call `algocoll`: up to three kernels, each given the keys, their count, a place for as many words of result and 64 bytes, zero before the first kernel of a run, in which one kernel leaves a collection for the next (`void *st[8]` in C, a value of the program's type in Rust); C writes its collections by hand and Rust uses those of its library.

`bench/par/<name>_seq.aiw` and `<name>_par.aiw` are one computation without and with tasks. `bench/async/` has the programs of async functions; `echo.aiw` and `num.aiw` there are files that the programs `use`, and `echod.c` is the server in C that `docs/performance.md` compares with. `bench/nd/mmtasks.aiw` times `ndmatmulp` against `ndmatmul` for a shape, threads and tasks given as six words on stdin, the first call apart from the later ones, each result compared with that of `ndmatmul` outside the clocks (the first, the last, and every later call that would be the fastest, before its time is taken), and prints the row blocks that `ndruns` plans (no count of tasks that ran: one block, or a block whose `spawn` fails, is computed by the caller); `bench/nd/mmtasks.sh`, run by hand, writes the table of `docs/performance.md` with it.

## Tests

`make test` checks that the programs are right and the tools behave; it measures nothing and needs no network. A test `tests/<name>.aiw` with its `.out` is run by `tests/run.sh`, a copy of the runner of the main repository (`algo_clock`, `lib_fmt`).

`tests/algo.sh` checks the algorithm programs: for the counts 0, 1, 2, 3 and 1000 in each of the four shapes the result of a sort must be what `sort` makes of its input, the indices of `bsearch` those that awk finds by looking at every word, the values of `hashtab` and the two orders of `graph` those of an awk program with a table, a queue and a stack of its own (for `hashtab` also at two counts whose lookups go round the end of the table, which the script proves with a table in shell), and the checksum the one the script computes; it asks the same of the C programs if there is a `gcc` and of the Rust programs if there is a `rustc`. Every kernel time of a run of each program must be more than 0 and less than ten seconds. It requires the keys reversed, in order and sorted of `collseq`, and of `collmap` the number of keys, the last index of each and a 1 where a key is met first, as awk has them, at the counts 0, 1, 2, 3, 1000 and 3000. For the array programs it reads their input as doubles in awk, computes the sums and products with loops of its own and requires each word of the result within 1e-9 of the sizes, for the counts 0, 1, 2, 3 and 1000, or 30 as a side.

`tests/par.sh` runs `build/tools/par --check`: each `bench/par/<name>_par.aiw` must print what `<name>_seq.aiw` prints, at six pairs of thread and task counts; it then runs the tool where the program with tasks prints something else and requires the six failures to be reported with the status 1. `tests/async.sh` compiles the programs of `bench/async/`, which use the async runtime with arguments and across processes: `timer` must print its five lines in the time of its timers, `ping` (a task a connection that sends 64 bytes and reads them back) must finish 4 connections and then 300 against the server in its own process, and against `echod` running as a process on a port the kernel chose, and fail once that is killed; `switch`, `mem` and `hop` must print figures of the right kind, and `calc`, a task that computes between its waits, the sum the script computes itself. `tests/tools.sh` requires the prerequisites the Makefile gives each `build/tools/<name>` to be its source and every file that source reaches with `use`, so a new `use` needs the file added there, and checks what `perf` and `nptime` say to arguments they refuse. `tests/tokens.sh` checks `tokens`: for the texts `tests/tokens/<name>.txt` it must print the token numbers in `<name>.ids`, which tiktoken gave; the count of a file without comments must be that of its code; bytes that are no UTF-8 are refused; the table must have a count for a program of `bench/` and for an example of the main repository, which the tool finds by the extension of their names; without the pinned files it prints `skip` and succeeds. `tests/indent.sh` runs `build/tools/indent --check` on every `.aiw` file that git has.

## Tools

A tool is `tools/<name>.aiw`, compiled to `build/tools/<name>`. The entry point handles arguments, messages and the exit status. A tool keeps its state in `var`s named with a `g` in front and its variables off those names; text is an address and a length, and a place in a buffer that may grow is an offset.

`perf` keeps its programs, their inputs and expected results as data of its own, and checks, times and reports in separate functions. The algorithm programs are its kind 2: each row of its table `work` is an input (program, count, shape, runs), such a program writes the nanoseconds of its kernels behind a checksum, and `perf` reads them after every run and prints them in a table apart from the elapsed time; `--qsort` adds `bench/qsort.c` to the sorts. `--places` compiles each program asked for eight times with `build/ailc`, with 1 to 29 bytes of `data` before its first line (`placed`), times each as it times `build/<name>` (`timed`) and prints AIL / C of each (`figure`: for an algorithm program the geometric mean over its kernels) with the least, the median and the most: the way to compare two compilers on a short loop. A new algorithm program is a name in `progs`, its kernels in `pkern` and `knames`, rows of `work`, its name in `ALGO` of the Makefile with the files it names with `use`, and its checks in `tests/algo.sh`.

`par` compiles, checks and times the programs of `bench/par/` and asks `build/tools/tokens` for the counts of its last table. `tokens` keeps the choice of the programs (every `.aiw` of `bench/` and of `ail/examples/`), the removal of comments and the table. `nptime` times NumPy on the kernels and sizes of the rows that `perf` has for the three array programs: NumPy is called by Python, so for each row of its table the tool writes a program for `python3` (the arrays, then 17 rounds of the runs, each printed in nanoseconds), runs it bound to one CPU and prints the median of the last 15 rounds; it replaced a Python script on 2026-11-21. `tools/claudetokens.sh`, run by hand only, sends the code of the programs (`build/tools/tokens --code <mark> file`) to a service that counts them with Claude's tokenizers, for the comparison in `docs/tokens.md`.

`tools/lib/fmt.aiw` (prefix `fmt`) has `fmtfix`, a double with up to three digits after the point as `%.2f` prints it, tested by `tests/lib_fmt`. `tools/lib/o200k.aiw` (prefix `tk`) is the tokenizer of the encoding o200k_base: `tkinit` reads the tokens and the categories of the characters from the two files that `tools/pins/MANIFEST` pins by SHA-256 and address (`tools/pin.sh <name>` fetches one into `tools/pins/`, which git ignores but for the manifest), and `tkencode` cuts a UTF-8 text into pieces by the rules written at the top of the file, a hand-written matcher for each alternative of the pattern of the encoding, and each piece into tokens by merging the pair of the lowest rank, kept in a heap. The Unicode version is 16.0.0, the one whose categories tiktoken 0.14.0 splits by; with any other the fixtures of `tests/tokens.sh` and a comparison with tiktoken fail.

## Knowledge base

`.agents/knowledge/INDEX.md` lists the pages: why the repository is linked to the main one as it is, the harness, how time is measured and what makes a figure wrong, the tokenizer, the checks. The pages of the main repository keep the decisions about the language and the libraries that the measurements led to.
