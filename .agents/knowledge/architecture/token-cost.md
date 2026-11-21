---
title: Token cost
last-verified: 2026-11-21
sources: [conversation 2026-11-21, tools/tokens.aw, tools/lib/o200k.aw, tools/pins/MANIFEST, tests/tokens.sh, tools/claudetokens.sh, docs/tokens.md]
status: current
---
# Token cost

**Summary.** `tools/tokens.sh` prints the tokens of every program in AIL, C, Rust and
assembly without comments and blank lines, counted by `build/tools/tokens`, a tokenizer of
the encoding `o200k_base` written in AIL that gives the numbers of `tiktoken`.
`docs/tokens.md` has the results and the counts that decided the syntax of the language.

## Decisions
- **The whole tokenizer is here**, with its pins, fixtures and test: nothing in the main
  repository used `tools/lib/o200k.aw` once the tool had moved (2026-11-21).
- **Two pinned files** (`o200k_base.tiktoken`, `UnicodeData.txt` of Unicode 16.0.0) in
  `tools/pins/`, by SHA-256 in the manifest, fetched once by `tools/pin.sh` and ignored by
  git. Without them `tests/tokens.sh` prints `skip`, so `make test` needs no network.
- **Fixtures from tiktoken 0.14.0** in `tests/tokens/`: another Unicode version splits
  otherwise and fails them.
- **Claude's tokenizers** are asked through an outside service by `tools/claudetokens.sh`,
  by hand only; the user accepted that for the sources of the benchmark programs and nothing
  else. It is no part of any other command.

## How to apply
- Before choosing between two spellings, count both: `build/tools/tokens -c '#' file`.
- After changing a program, run `tools/tokens.sh` and update the table of `docs/tokens.md`.
- The rules for new syntax that the counts gave are in the main repository:
  [token cost measurement](https://github.com/CristianosLeite/ail/blob/main/.agents/knowledge/architecture/token-cost-measurement.md).
