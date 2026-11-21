---
title: Link to the main repository
last-verified: 2026-11-21
sources: [conversation 2026-11-21, Makefile, tools/ail.sh, ail.pin]
status: current
---
# Link to the main repository

**Summary.** The benchmarks left the main repository on 2026-11-21. This repository has no
copy of the compiler, the libraries or the ten small examples: `tools/ail.sh` puts the main
repository at `ail/`, as a clone of the address and revision in `ail.pin` or as a link to a
checkout named with `make AIL=<path>`, and makes the links `lib` and `tools/lib/rng.aw`.

## Decisions
- **A clone from the published address by default** (user, 2026-11-21): the first plan was a
  variable that named a directory beside this one, `../ailang`. Rejected because a developer
  who clones only this repository could then build nothing. `make` alone must work.
- **A pinned revision in `ail.pin`**, on the model of `seed/MANIFEST` of the main repository:
  the figures in `docs/` belong to a compiler, and a clone that followed a branch would give
  other figures from one day to the next without a change here.
- **No git submodule:** it gives the same pin, but asks every developer for
  `--recurse-submodules` and makes measuring a compiler in progress a change of the index.
  A link made by `make AIL=<path>` does that with nothing to commit.
- **A link is left alone, a clone is moved** to the pinned revision by the next `make`, and a
  clone with changes of its own is an error and never overwritten.
- **`lib` is a link at the top** because the compiler opens `use "lib/std/io.aw"` from the
  directory it runs in, and the libraries name each other that way. So the `use` lines of the
  programs are those they had in the main repository.
- **No `examples/` here** (user, 2026-11-21): `examples/` exists in the main repository
  only. The eleven programs that exist to be measured are `bench/<name>.aw`, beside their C
  and Rust; the ten small ones are read as `ail/examples/<name>.aw`.
- **The recipe of `build/ailc` names `make` and not `$(MAKE)`:** `tests/tools.sh` reads the
  rules with `make -pn`, which runs a recipe that has `$(MAKE)` and would then print the
  rules of the main repository too.

## Gotchas
- The revision in `ail.pin` must exist in the published repository. One that is only in a
  local checkout builds with `make AIL=<path>` and fails for everyone else.
- A change there of a path under `lib/`, of the name of a small example, or of
  `tools/lib/rng.aw` breaks the build here; the main `AGENTS.md` says so under "Measurements".
- `make clean` in the main checkout removes the compiler that `build/ailc` links to; the next
  `make` here builds it again.
