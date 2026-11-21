---
title: Checks
last-verified: 2026-11-21
sources: [conversation 2026-11-21, Makefile, tests/]
status: current
---
# Checks

**Summary.** `make test` here checks that the measured programs compute what they should and
that the tools behave. It measures no time, counts no token of the corpus, and needs no
network, `gcc` or `rustc` (the C and Rust programs are checked when their compilers are
there).

## Decisions
- **The checks of the programs moved with the programs** (user, 2026-11-21): `tests/algo.sh`,
  `tests/par.sh` and `tests/async.sh` were part of `make test` of the main repository. That
  suite no longer runs them, so a change of the compiler or of `lib/` that breaks a program
  here is found only by running `make test` here against that checkout (`make AIL=<path>`).
  The libraries keep their own tests there (`lib_*`, `async_*`, `spawn_*`, `task_*`).
- **References share nothing with the program**: awk, `sort` and shell compute what a
  program must print.
- **`tests/run.sh` is a copy** of the runner of the main repository, for the two tests that
  are a program and its output (`algo_clock`, `lib_fmt`).
- **`tests/tools.sh`** compares the prerequisites of each tool in the Makefile with its `use`
  lines, and checks only the refusals of `perf` and `nptime`, which need other compilers or
  Python to do their work.

## Not checked
- That a figure in `docs/` is still what the tools print.
- `nptime` beyond its arguments, `tools/claudetokens.sh`, `bench/nd/mmtasks.sh`.
- A clone made by `tools/ail.sh` from the published address; see the open items.
