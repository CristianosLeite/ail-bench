# ail-bench

The benchmarks of [AIL](https://github.com/CristianosLeite/ail), a programming language designed to cost few tokens when an AI writes it: what its programs cost in tokens and in time against the same programs in C, Rust and assembly, and the tools that measure both. The compiler, the libraries and the ten small examples are those of the main repository; this one fetches it and has nothing of it copied.

The results are in two documents:

- [Token cost](docs/tokens.md): AIL against C, Rust and assembly with the tokenizer `o200k_base` and with Claude's, and the counts that decided the syntax
- [Performance](docs/performance.md): the method, and every measurement since the first, with what each changed in the compiler or the libraries

## Requirements

x86-64 Linux, `git`, `make` and what the main repository needs to build its compiler (`curl` or `gh` once, for the seed). The comparisons need `gcc`, `rustc` and GNU `as` and `ld`; `make test` checks the C and Rust programs only if those compilers are there. The tokenizer reads two pinned files that `tools/tokens.sh` fetches once with `curl`. `build/tools/nptime` needs `python3` with NumPy.

## The main repository

`make` puts the main repository at `ail/`, which git ignores:

```
make                    # clone the address in ail.pin at the revision pinned there, build its compiler, build everything here
make AIL=../ail         # use a checkout of your own instead: ail becomes a link to it, until you remove it
```

`ail.pin` holds the address and the revision this repository was last checked against. A clone at another revision is moved to the pinned one by the next `make`; a link is left alone, so that a compiler being worked on can be measured. `lib` and `tools/lib/rng.aw` are links into `ail/` that `make` creates, so that a `use "lib/std/io.aw"` here names the same file as there.

## Commands

```
make                 # build build/<name> of every program and build/tools/<name> of every tool
make test            # check the programs and the tools; measures nothing
make test T=lib_fmt  # one test of tests/<name>.aw
tools/bench.sh       # every C, Rust and assembly program must behave like its AIL program (needs gcc, rustc)
tools/tokens.sh      # the token table of docs/tokens.md (fetches the pinned files once)
build/tools/tokens -c '#' file.aw    # the tokens of a file without its comments; --ids file prints their numbers
build/tools/perf     # time every program against C (gcc -O3), Rust (rustc -O) and assembly
build/tools/perf --quick quicksort   # one program, fewer rounds
build/tools/perf --qsort quicksort   # the sorts also against qsort of the C library
build/tools/perf --places heapsort   # AIL / C with the program compiled at eight places: compares two compilers on a short loop
build/tools/par      # time the task programs against their sequential forms at 1 to 20 threads
build/tools/par --grid --runs 3      # the gain at every number of threads with every number of tasks
bench/nd/mmtasks.sh  # a matrix product with tasks, the table of docs/performance.md
build/tools/nptime   # NumPy on the kernels of the array programs (needs python3 with numpy)
tools/claudetokens.sh  # by hand only: counts the programs with Claude's tokenizers through an outside service
make clean
```

Run everything from the top of the repository. Times are worth reading only on a quiet machine; `perf` and `nptime` bind themselves to one CPU (`--cpu N`).

## Layout

- `bench/<name>.c`, `.rs`, `.s`: the programs in C, Rust and assembly. Ten mirror `examples/<name>.aw` of the main repository (`hello`, `echo`, `count`, `loop`, `fact`, `compute`, `sieve`, `collatz`, `fnv`, `isort`)
- `bench/<name>.aw`: the AIL programs that exist to be measured: the algorithms (`quicksort`, `mergesort`, `heapsort`, `bsearch`, `hashtab`, `graph`), arrays of doubles (`ndelem`, `ndreduce`, `ndmatmul`) and collections (`collseq`, `collmap`). Each is the call of a kernel of the library and of the harness
- `bench/algo/`: the harness in the three languages (`harness.aw`, `harness.h`, `harness.rs`): the input from a seed, the clock around each kernel, the result
- `bench/par/`: programs without and with tasks, timed by `par`
- `bench/async/`: programs of async functions: a timer, a TCP echo server (`echod`, also in C) with its load (`ping`), and what a wait, a sleeping task and a hop to another thread cost
- `bench/nd/`: the matrix product with tasks
- `tools/`: `perf`, `par`, `tokens` and `nptime` in AIL, built as `build/tools/<name>`; `tools/lib/` has what they share (`fmt.aw`, `o200k.aw`, the tokenizer); `ail.sh` fetches the main repository, `pin.sh` the files of `tools/pins/MANIFEST`
- `tests/`: the checks of `make test`
- `docs/`: the results
- `AGENTS.md`, `.agents/knowledge/`: guidance and notes for AI coding agents

## License

[MIT](LICENSE).
