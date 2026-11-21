# Knowledge index

The decisions about the language, the compiler and the libraries that the measurements led to are in the knowledge base of the main repository (https://github.com/CristianosLeite/ail, `.agents/knowledge/`). The pages here are about measuring.

## architecture
- [Link to the main repository](architecture/link-to-ail.md) — a pinned clone or a link at `ail/`, made by `tools/ail.sh`; why not a submodule, a copy or a directory beside this one; what is read by name from there
- [Harness and programs](architecture/harness-and-programs.md) — where an AIL program is (`bench/` or `ail/examples/`), one harness in three languages with the same bits of input, kernel times behind a checksum
- [Measuring time](architecture/measuring-time.md) — the method of `perf`, what makes a figure wrong (placement, the clock, a busy machine), `--places`, `par`, `nptime` and why it still runs Python
- [Token cost](architecture/token-cost.md) — the tokenizer in AIL and its pinned files, fixtures from tiktoken, the count with Claude's tokenizers, what leaves the machine

## testing
- [Checks](testing/checks.md) — what `make test` checks here, what it leaves to the main repository, why it measures nothing

## status
- [Open items](status/open-items.md) — pending work
