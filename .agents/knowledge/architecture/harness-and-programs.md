---
title: Harness and programs
last-verified: 2026-11-21
sources: [conversation 2026-11-21, bench/algo/harness.aiw, tools/perf.aiw, tests/algo.sh, docs/performance.md]
status: current
---
# Harness and programs

**Summary.** A program is measured in up to four languages. Its AIL source is
`bench/<name>.aiw` when it exists to be measured and `ail/examples/<name>.aiw` when it is an
example of the language; the tools look in that order. The algorithm, array and collection
programs are a kernel of the library and a call of one harness that the three languages have
alike, so what differs between the languages is the kernel.

## Decisions
- **One harness, three sources** (`harness.aiw`, `harness.h`, `harness.rs`): the input comes
  from a seed by splitmix64, so every language has the same words, and a number for the array
  programs is the lowest 20 bits of a word, divided by 1024, less 512, so every language has
  the same doubles and, adding in the same order, the same bits of result. `perf` requires the
  same checksum of all before it times anything.
- **The program times its kernels itself** and writes the nanoseconds behind the checksum:
  the time of a process would be that of making the input. `perf` reads them after each run.
- **The AIL kernels are calls of `lib/algo`, `lib/nd` and `lib/std`**, since what is to be
  known is what the library costs a program; C writes its loops and collections by hand and
  Rust uses its standard library.
- **Token counts of those programs are those of the call, not of the algorithm**, since the
  kernels moved into the library; `docs/tokens.md` keeps them apart from the corpus of ten,
  whose totals stay comparable with earlier counts.

## How to apply
- A new program: `bench/<name>.aiw`, `.c`, `.rs`; a name in `ALGO` of the Makefile with its
  `use` files; `progs`, `pkern`, `knames` and rows of `work` in `tools/perf.aiw`; a reference
  in `tests/algo.sh` that shares nothing with the program (awk, `sort`).
- After changing an AIL program, change its equivalents and run `tools/bench.sh`.
- More on the reasons: [algorithms and collections](https://github.com/CristianosLeite/ail/blob/main/.agents/knowledge/architecture/algorithms-and-collections.md) and
  [numerical arrays](https://github.com/CristianosLeite/ail/blob/main/.agents/knowledge/architecture/numerical-arrays.md) in the main repository.
