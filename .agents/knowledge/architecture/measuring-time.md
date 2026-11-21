---
title: Measuring time
last-verified: 2026-11-21
sources: [conversation 2026-11-21, docs/performance.md, tools/perf.aw, tools/par.aw, tools/nptime.aw]
status: current
---
# Measuring time

**Summary.** `perf` builds C with `gcc -O3`, Rust with `rustc -O` and assembly with `as` and
`ld`, requires every executable to give the expected output, then times child processes with
the monotonic clock, bound to one CPU, each round running every language once in an order
shuffled from a fixed seed. A figure is a median with its quartiles. `docs/performance.md` has
the method in full and the current result of every measurement.

## What makes a figure wrong
- **The place of a loop in the file.** A short loop moved by a few bytes changed its time by
  up to a fifth, more than most changes of the compiler that were being judged. Since the
  compiler aligns the first line of a loop to 64 bytes it is 1 to 3%. `perf --places <name>`
  compiles a program eight times behind 1 to 29 bytes of `data` and prints least, median and
  most: compare two compilers by those, never by one build.
- **The clock.** The harness reads the clock through the vDSO as the libraries of C and Rust
  do; a system call for each reading was charged to AIL alone on short kernels.
- **A busy machine.** The quartiles show it; run again rather than explain a wide one.
- **Results computed while compiling.** Every input is read at run time.

## Other tools
- `par` times `bench/par/<name>_par` against `_seq` at 1 to 20 threads; `--grid` every
  number of threads with every number of tasks. `--check` is what `make test` runs.
- `nptime` (2026-11-21, replacing `tests/nd/bench.py` at the user's request to have it in
  AIL) times NumPy on the rows of the array programs. NumPy can only be called by Python, so
  the tool writes a short program for `python3` for each row and keeps the rest itself: the
  table of rows, the CPU, the 17 rounds, the median, the format. The clock is Python's,
  inside the process, because the start of the interpreter and the import cost far more than
  a row. Its figures agreed with those of the script on the same machine.

## How to apply
- Report the command, the day and the revision of the main repository with a figure.
- `docs/performance.md` has the current figures only (the user's decision of 2026-11-21, when
  every measurement was run again and the sections that recorded the steps of the compiler
  were dropped; they are in the history of the file up to `cb14f3b`). A measurement that is
  run again replaces its section; a new one gets a section. The async figures and the
  processor time and system calls of the echo servers are run by hand, with the commands of
  that section, `/proc/<pid>/stat` and `strace -c`.
- The main repository links to sections of that file by anchor; the anchors of the dropped
  sections no longer exist.
- The compiler decisions that came out of these figures:
  [generated code after the algorithm measurements](https://github.com/CristianosLeite/ail/blob/main/.agents/knowledge/architecture/generated-code-follow-up.md).
