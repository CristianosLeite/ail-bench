# Token cost

> These measurements were made while the programs were part of the [main repository](https://github.com/CristianosLeite/ail), until 2026-11-21. In the sections written before that day `examples/` is `examples/` of the main repository for the ten small programs and `bench/<name>.aw` here for the algorithm, array and collection programs, `docs/language.md` and `docs/compiler.md` are documents there, and the tests named `lib_*` and `tests/nd/gen.py` are there too.

AIL exists to be cheap in tokens, so syntax choices are decided by measurement. `tools/tokens.sh` counts the tokens of each example in `examples/` and of the equivalent C, Rust and assembly programs in `bench/`, with comments and blank lines removed: the ten programs of the corpus and, [apart from it](#result), six algorithm programs. It counts with `build/tools/tokens`, a tokenizer of the `o200k_base` encoding written in AIL (`tools/tokens.aw`, `tools/lib/o200k.aw`), which gives the token numbers that `tiktoken` gives: it was compared with it on every character of Unicode in ten forms, on the text files of the repository and on random texts, and `tests/tokens.sh` keeps ten texts with the numbers `tiktoken` gave. It reads the tokens of the encoding and the character categories of Unicode 16.0.0 from two files that `tools/pins/MANIFEST` pins by checksum; `tools/tokens.sh` fetches them on first use (that step needs network access). `build/tools/tokens -c '#' file ...` counts single files without their comments, and `--ids file` prints the token numbers of a file. Until 2026-11-20 the count was `tiktoken` itself in a Python environment; the tables below are the same with either. The counts are those of one tokenizer; another model's tokenizer gives other numbers, but the comparison between languages is what matters here.

```
tools/tokens.sh             # the table below
tools/tokens.sh hello fact  # some programs only
tools/bench.sh              # check that bench/ behaves like examples/ (needs gcc and rustc)
build/tools/perf               # time the same programs, see performance.md
```

## Result

| program | AIL | C | Rust | asm |
|---|---:|---:|---:|---:|
| collatz | 121 | 119 | 165 |  |
| compute | 91 | 106 | 120 | 229 |
| count | 118 | 141 | 102 | 232 |
| echo | 56 | 58 | 86 | 150 |
| fact | 41 | 44 | 55 | 101 |
| fnv | 144 | 167 | 126 |  |
| hello | 18 | 29 | 45 | 78 |
| isort | 170 | 239 | 250 |  |
| loop | 44 | 70 | 56 | 135 |
| sieve | 138 | 145 | 186 |  |
| total | 941 | 1118 | 1191 | 925 |
| relative to AIL | 1.00 | 1.19 | 1.27 | 2.51 |

This is the fixed corpus that a change of syntax is judged on: five short programs that are mostly system calls, and five that compute (`compute`, `sieve`, `collatz`, `fnv` and `isort`, described in [performance.md](performance.md)). The last four have no assembly equivalent; a language is compared with the AIL total of the programs it has, so the assembly figure is over six programs. Since 2026-11-21 `isort` calls `sortins` of [`lib/algo/`](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#the-algo-library), 110 tokens there, where it had the loop in the program: it was 219 tokens and the total 990 (C 1.13, Rust 1.20), which are the figures the measurements below and the earlier sections of [performance.md](performance.md) name. The C and Rust programs keep their loop, so that row now compares a caller with a kernel; the other nine are as they were.

The examples are written with expressions, `x=e`, `x+=e`, `while` and `if` on a value since 2026-11-20. They are indented with a tab for each block, which was 20 of the 990 tokens when it was measured ([below](#indentation-measured-on-2026-11-20)); the other languages are counted with their indentation too. AIL costs 16% fewer tokens than C that does the same, 21% fewer than Rust and 60% fewer than assembly (11% and 17% with the loop of `isort` in the program). It is cheaper than C in every program but `collatz`, which is two tokens dearer since its blocks are indented, and dearer than Rust only in `count` and `fnv`, where Rust formats a number or reads a file with one call. Written with one operation a line, as the language first was, the same ten programs were 1313 tokens, 17% more than C; the [count](#expressions-measured-on-2026-11-20) below chose the spelling that made the difference.

Since 2026-11-20 `examples/` also has six algorithm programs, which `tools/tokens.sh` lists with the ten: a kernel each and its call of a harness that the three languages have alike (`bench/algo/`), so their count is that of the algorithm. They are not part of the corpus above, whose totals stay comparable with the earlier measurements.

| program | AIL | C | Rust |
|---|---:|---:|---:|
| bsearch | 129 | 156 | 163 |
| graph | 373 | 345 | 405 |
| hashtab | 238 | 280 | 280 |
| heapsort | 211 | 237 | 271 |
| mergesort | 309 | 298 | 340 |
| quicksort | 264 | 297 | 324 |
| total | 1524 | 1613 | 1783 |
| relative to AIL | 1.00 | 1.06 | 1.17 |

Since 2026-11-21 there are also two programs that time the collections of `lib/` ([performance.md](performance.md#collections-2026-11-21)): `collseq`, 381 tokens in AIL, 710 in C and 299 in Rust, and `collmap`, 224, 1009 and 261. The AIL and Rust programs call collections that their libraries have, and C writes its own, which is its count; they are not part of the corpus either.

Since 2026-11-21 there are also three programs on arrays of doubles, whose kernels in AIL are calls of [`lib/nd/`](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#the-nd-library) and in C and Rust plain loops: `ndelem` 215, 197 and 200 tokens, `ndreduce` 252, 276 and 268, `ndmatmul` 141, 163 and 142, together 608, 636 and 610. A call of a kernel is 5 to 7 tokens and each array 12 more before its first use, which comes to what the loops cost ([performance](performance.md#numerical-arrays-2026-11-21)). They are not in the table above, which stays that of the six.

Since 2026-11-21 the kernels of the six, and the loop of `isort`, are in [`lib/algo/`](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#the-algo-library) and the programs call them, so `tools/tokens.sh` now counts a caller where C and Rust still have the algorithm. The table above stays as the count of the algorithms written in each language. What a caller costs and what the library costs, without comments:

| program | AIL before | caller | its functions in `lib/algo/` | C | Rust |
|---|---:|---:|---:|---:|---:|
| bsearch | 129 | 79 | 85 | 156 | 163 |
| graph | 373 | 37 | 464 | 345 | 405 |
| hashtab | 238 | 37 | 296 | 280 | 280 |
| heapsort | 211 | 30 | 252 | 237 | 271 |
| mergesort | 309 | 30 | 393 | 298 | 340 |
| quicksort | 264 | 47 | 303 | 297 | 324 |
| total | 1524 | 260 | 1793 | 1613 | 1783 |

- **A caller is 30 to 47 tokens** where the harness can call the library function as it is or through an adapter of one line, a sixth of the C program; `bsearch` keeps its loop over the keys and is half of it.
- **The library costs more than the kernel did**, 1793 tokens against about 1300 for the same statements in the programs: every variable has the prefix of its file, and `sortp` is two tokens where `p` is one. That is the price of names that cannot meet a `var` of the program, paid once by who writes the library and not by who calls it.
- **`isort`**, one of the ten of the fixed corpus, went from 219 to 170 tokens by calling `sortins` (110 in the library); the corpus is 941 tokens with it where it was 990, as [the table of the ten](#result) now has it. Its loop was the only kernel among the ten, so the other nine are as they were, and a change of syntax is still judged on the ten.

On the kernels in the programs, as the first table has them, AIL is 6% cheaper than C and 15% cheaper than Rust on the six, less than on the ten: these are loops over arrays, where C writes `a[i]` and `*k++ = *j++` and AIL a `load` and a `store` on lines of their own. It is dearer than C in `mergesort` and `graph`, the two with the most such lines. With all sixteen the totals are 2506, 2731 and 2974.

The first measurement, on 2026-11-19 before any of the backlog, gave 411 for AIL (C 0.83, Rust 0.84, assembly 1.69). The difference is the compare-and-jump statements, which the measurement below asked for, and the syscall names in `hello`.

## Claude's tokenizers, measured on 2026-11-21

Every count above is of `o200k_base`, an encoding of OpenAI, taken as a stand-in: the language is meant to be cheap for a model that writes it, and the models of Anthropic cut a text otherwise. There is no file of their tokens to count with on a machine, so `tools/claudetokens.sh` asks: it sends the code of each program, the text `build/tools/tokens --code` prints, to claudetokenizer.com, a site of a third party that passes it to Anthropic's token counting, and takes the counts of two models, `claude-fable-5`, which has the newer tokenizer, and `claude-sonnet-4-6`, which has the one before. It is run by hand, since it sends the sources out, which nothing else here does. The answer also has the count of `o200k_base`, and for all 63 files it was the count of `build/tools/tokens`, so the text arrived as it was counted here. What a message costs without its text, 5 and 6 tokens, is taken off; the service does not count the last line end of a text, so a count may be one token short, in every language alike.

The ten programs of the corpus:

| program | AIL | C | Rust | asm | AIL | C | Rust | asm |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| | Fable 5 | | | | Sonnet 4.6 | | | |
| collatz | 152 | 162 | 232 |  | 138 | 144 | 204 |  |
| compute | 115 | 150 | 182 | 302 | 102 | 130 | 148 | 264 |
| count | 144 | 167 | 147 | 310 | 129 | 165 | 124 | 265 |
| echo | 70 | 75 | 123 | 208 | 61 | 74 | 102 | 180 |
| fact | 57 | 66 | 75 | 134 | 49 | 57 | 68 | 111 |
| fnv | 179 | 230 | 179 |  | 160 | 200 | 152 |  |
| hello | 24 | 41 | 64 | 112 | 20 | 40 | 54 | 93 |
| isort | 214 | 320 | 348 |  | 195 | 283 | 304 |  |
| loop | 53 | 88 | 75 | 185 | 48 | 86 | 66 | 157 |
| sieve | 175 | 207 | 261 |  | 156 | 176 | 230 |  |
| total | 1183 | 1506 | 1686 | 1251 | 1058 | 1355 | 1452 | 1070 |
| relative to AIL | 1.00 | 1.27 | 1.43 | 2.70 | 1.00 | 1.28 | 1.37 | 2.62 |

| relative to AIL | C | Rust | asm |
|---|---:|---:|---:|
| `o200k_base` | 1.19 | 1.27 | 2.51 |
| Claude Sonnet 4.6 | 1.28 | 1.37 | 2.62 |
| Claude Fable 5 | 1.27 | 1.43 | 2.70 |

- **The order of the languages holds, and AIL is further ahead.** With Claude's tokenizers it costs 21 to 22% fewer tokens than C where `o200k_base` says 16%, and 27 to 30% fewer than Rust where it says 21%. The stand-in understated the gain.
- **Every text costs more.** The corpus in AIL is 941 tokens of `o200k_base`, 1058 of Sonnet 4.6 and 1183 of Fable 5, 12% and 26% more; over all 63 files the newer tokenizer gives 1.33 times the count of `o200k_base` and 1.10 times that of the older one, about alike in the four languages (1.33 to 1.37).
- **The programs that were dearer in AIL are not any more.** `collatz` was two tokens above its C program and is 10 below with Fable 5 and 6 below with Sonnet 4.6. Rust was cheaper for `count` and `fnv`: with Fable 5 `count` is 3 tokens cheaper in AIL and `fnv` the same, with Sonnet 4.6 Rust keeps both, by 5 and 8.
- **The array programs are the exception.** `ndelem`, `ndreduce` and `ndmatmul` together are 608, 636 and 610 tokens of `o200k_base` in AIL, C and Rust; with Sonnet 4.6 they are 709, 774 and 750, and with Fable 5 817, 800 and 792: there AIL costs 2 to 3% more than the loops of C and Rust. Their lines are calls with the prefixed names of `lib/nd/` (`ndwrap`, `ndmatmul`), and the newer tokenizer cuts such names into more pieces.
- **The callers of `lib/algo/`** are 260 tokens against 1613 and 1783 in `o200k_base` and 376 against 2123 and 2391 with Fable 5; that compares a call with an algorithm, as said above, and changes with no tokenizer.

What this does not say: the rules [above](#what-this-decides), fewer lines before shorter words and a name before a number, were found by looking at the tokens of `o200k_base` one by one, and the service gives a count and no tokens. They were not looked at again. The comparison between the languages, which is what the counts are for, holds with the three tokenizers.

## Where the tokens go

Measured on the first `compiler/ailc.aw` without comments: 6181 tokens in 1107 lines, 5.6 a line.

- **The line end is a token**, and so is every statement that an expression language would nest: 1096 of the 6181 tokens are line ends. One operation per line is the main cost against C.
- **`as` is a token** on 422 lines. It is the price of naming every intermediate result.
- **A number after a space is two tokens** (the space, then the digits), and a long decimal is more: `4294967295` is four tokens where `0xffffffff` is two. A name after a space is one token.
- **A comparison used once costs twelve tokens**: `gt n 4096 as e` and `jnz e fail`, against seven for a single compare-and-branch statement. Stage1 has 55 such pairs. `jnz` and `jz` are two tokens each.
- **Working around limits costs lines**: 120 lines of stage1 build constant tables from decimal words, and 50 are calls to the accessors of the packed state buffer.

## What this decides

- Fewer lines matter more than shorter words: prefer one statement that does the work of two (a compare-and-branch, a global scalar read directly as an argument) over abbreviations.
- Keep keywords that are single tokens (`load`, `store`, `data`, `const`, `call`, `label`); a suffix digit costs one more token (`load4` is `load` and `4`) and a suffix letter the same (`loadb`).
- Hex notation is worth having for large values.
- Named constants and named data replace numbers, which are the most expensive kind of argument.

Run the script again after a change of syntax and update the table when an example changes.

`tools/infix.aw` (`build/tools/infix in.aw out.aw`) rewrites a source written with statements: each statement that computes as an assignment, a label loop with its test at the top as `while`, and a variable that is assigned on one line and read once on the next, and not again, folded into that line, repeatedly. A `var` is never folded, wherever it is declared: the tool reads the files the source names with `use`, and files named after `out.aw` for a source that another file includes. On the ten examples as they were written with statements it gives 1031 tokens from 1313; written by hand they are 970 (990 since they are indented), the difference being lines that the tool keeps for their comments and loops it does not restructure. It was checked by rewriting the 310 runnable test programs it changes: each behaves as before. On `compiler/ailc.aw` it gave 20172 tokens from 25635, 21% fewer.

## Control flow, measured on 2026-11-20

Before choosing new forms of control flow, the cost of the existing ones was counted in `compiler/ailc.aw` and the examples together: 13447 tokens in 2443 lines.

- Jumps, labels and comparisons were 3894 tokens, 29% of all.
- 160 comparisons were followed by a `jz` or `jnz` on their result. One statement for the pair saves 857 tokens: 6.4% of all, 22% of the control flow.
- 152 of the 242 labels were the target of a single forward conditional jump, the shape of an `if`. A block that needs no label name saves 602 tokens: 4.5% of all, 15% of the control flow.
- 31 labels were the target of a single backward jump, the shape of a loop. A loop statement would save little, since a loop still needs its test and often a second exit.

So the compare-and-jump statements and the `if` blocks were added, and no loop statement then. `while` came with expressions (below): on programs that compute, a loop with its test in one line is worth more than it was in the compiler source, where most loops have a second exit. After rewriting stage1 with `tools/fuse.py` (now `tools/fuse.aw`), `compiler/ailc.aw` went from 13483 to 12305 tokens.

## Expressions, measured on 2026-11-20

The redesign adds infix expressions, and how they are spelled was decided by writing the ten programs of the corpus in each candidate and counting. The candidates differ in three things: whether the operators of an expression stand between spaces, what separates the arguments of a statement once an argument can be an expression, and how an assignment is written. All of them use `while` and an `if` whose value is an expression.

| spelling | example | total |
|---|---|---:|
| statements, as before | `mod x 2 as r` | 1313 |
| operators between spaces, `x = e`, a comma between arguments | `storeb out + 1, 48 + n / 100 % 10` | 1104 |
| the same without the comma | `storeb out + 1 48 + n / 100 % 10` | 1092 |
| the comma form with `set e as x` for assignment | `set i + 1 as i` | 1140 |
| operators touch their operands, spaces separate arguments, `x = e` | `storeb out+1 48+n/100%10` | 1024 |
| the same with `set e as x` | `set i+1 as i` | 1060 |
| the same with `x=e` | `x=x*3+1` | 988 |
| the same with `x+=e` beside it | `c+=1` | 975 |
| C | | 1118 |
| Rust | | 1191 |

Per program, the last AIL row against the others:

| program | before | chosen | C | Rust |
|---|---:|---:|---:|---:|
| collatz | 158 | 115 | 119 | 165 |
| compute | 114 | 91 | 106 | 120 |
| count | 189 | 118 | 141 | 102 |
| echo | 56 | 56 | 58 | 86 |
| fact | 52 | 40 | 44 | 55 |
| fnv | 189 | 141 | 167 | 126 |
| hello | 23 | 18 | 29 | 45 |
| isort | 297 | 220 | 239 | 250 |
| loop | 62 | 44 | 70 | 56 |
| sieve | 173 | 132 | 145 | 186 |
| total | 1313 | 975 | 1118 | 1191 |

What the counts show:

- **A space before an operator or a number is paid for.** `x = (x ^ x >> 13) * 1664525 + 1013904223` is 21 tokens and `x=(x^x>>13)*1664525+1013904223` is 16: a space and the digits after it are two tokens, an operator after a space is its own token, and touching characters often merge (`=(`, `)*`, `<n`, `=i`). The spaced forms take a tenth more than the touching ones.
- **Touching operators make the comma unnecessary.** If an expression has no space in it, the spaces separate the arguments as they always did, and `a -1` stays two arguments while `a-1` is one.
- **`x=e` beats `set e as x`** by about two tokens an assignment (`x=n` is 2, `set n as x` is 4), and `x+=e` saves one more where a variable is changed in place, 13 times in the corpus.
- **A load inside an expression would not pay by itself**: `v=[p-8]` and `load p-8 as v` are both 6 tokens. It would only save a line where the value is used once, which the corpus does three times.
- `if a<b` and `iflt a b` cost the same; the expression form needs no table of names.

## What this decides for expressions

- An expression is written without spaces: `a+b*c`, `(h^r)*0x100000001b3`. Spaces separate the arguments of a statement, as before, so every statement that exists keeps its meaning and no comma is needed.
- The operators, tightest first, are `* / %`, then `+ -`, `<< >>`, `&`, `^`, `|`, and last the comparisons `== != < <= > >=`, which are unsigned and give 1 or 0. That is the order of Rust, in which `x^x>>13` needs no parentheses; all are left-associative and parentheses group. A `-` followed by a digit where a value is expected is a negative number, as it is today.
- `name=expression` assigns, and `name+=expression` with any of the ten arithmetic operators before the `=` changes a variable in place. The whole statement is one word.
- `if` takes an expression like any statement takes one, and `while expression` ... `end` is a loop. The `ifeq` and `jlt` families, labels and jumps stay.
- A string stays a whole argument. Signed and float operations, the atomic ones, loads, stores and calls stay statements; a load in an expression is left out until programs show it paying.

With these the corpus was counted at 975 tokens, 26% fewer than before, 13% fewer than C and 18% fewer than Rust. The examples as they were then written, without indentation, came to 970: `isort` lost five more by leaving its loop through a jump instead of a second label.

## Indentation, measured on 2026-11-20

The compiler ignores indentation, so it is a cost without an effect on the program, paid for a source in which a block can be seen. The ten examples were counted without comments in four forms, the body of every `fn`, `while`, `if` and `else` one level deeper:

| indentation | tokens | against none |
|---|---:|---:|
| none | 970 | |
| one tab a level | 990 | +2.1% |
| two spaces a level | 1024 | +5.6% |
| four spaces a level | 1024 | +5.6% |

All four compile to the same bytes. Five programs have no block or lose nothing; `collatz` and `sieve` gain six tokens each, `isort` four, `fnv` three and `fact` one.

That decided the [source style](https://github.com/CristianosLeite/ail/blob/main/docs/language.md#source-style): one tab a level, set by `build/tools/indent`. The table at the top counts the examples as they are now, indented.

## Tasks, measured on 2026-11-20

`spawn` and `join` are statements so that a task costs what a call costs:

| form | tokens |
|---|---:|
| `call steps lo hi as x` | 7 |
| `spawn steps lo hi as h` | 7 |
| `join h as x` | 5 |
| the same with library functions only: `ref steps as p`, `call spawn p lo hi 0 0 0 as h`, `call join h as x` | 25 |

A task that is spawned and joined is 12 tokens in two lines, against 25 in three if `spawn` were only a function, which would also need every unused value written, since a call is checked against the parameters. A program pays 6 more once, for `use "lib/std/task.aw"`. What makes a program with tasks longer is not the forms but the cutting of the work: in the three programs of `bench/par/` the loop that cuts a range into pieces, keeps the handles and adds up the results is 90 of the 107 to 115 tokens added, counted before the programs were indented ([performance](performance.md#tasks-2026-11-20)). A helper that does it for any function would need a function as a value of `spawn`, which the statement does not take; that is left until more programs show the same loop.

## Async, measured on 2026-11-20

Before the compiler learned to write tasks that are polled, two programs were written in the forms it could accept, and counted without comments: `timer`, two tasks that print a line every 100 and 150 milliseconds, and `echo`, a server that accepts connections and sends back what each one sends, a task per connection.

| form | timer | lines | echo | lines |
|---|---:|---:|---:|---:|
| poll functions written by hand, which compile today | 180 | 34 | 291 | 53 |
| `async fn`, `call f x as h`, `await h as x` | 79 | 15 | 144 | 26 |
| the same with `async` left out, a function being async if it has an `await` | 78 | 15 | 142 | 26 |
| `async fn` and `await f x as r`, a call and its await in one statement | 74 | 14 | 124 | 22 |

By hand a task is a function that starts again at its first line at every poll: it keeps in its record where it was and what it needs, and every wait is a test, a return and a jump. That is 2.3 times the tokens of the form with `await`, and the part an AI gets wrong.

| statement | tokens |
|---|---:|
| `call asyncread c b 512 as h` | 12 |
| `await h as n` | 5 |
| `await asyncread c b 512 as n` | 12 |
| `call await h t as n r` and the `ifz r`, `ret 0`, `end` it needs by hand | 16 |
| `async fn conn c` | 5 |
| `fn conn c` | 4 |

What this decides:

- **`async fn name a b`** makes a function whose call returns at once with a handle, the function running as a task. The word costs one token a function. Leaving it out would save that token and nothing else, and cost two things: the first line of a function would no longer say what a call of it gives, and a plain function could not wait for a task itself, as the top level does, because an `await` in it would change what it is.
- **`await h as x`** waits for the task or event `h` and keeps its result: 5 tokens, 3 without the result.
- **`await name a b as x`** calls the function `name` and awaits the handle it returns, in one statement. A wait is mostly for something that was just started, and then the handle is a name that is written twice and a line that is paid for: the statement costs what the call alone costs, 5 tokens and a line less than the two. `echo` is 14% shorter with it and `timer` 6%.
- The two forms of `await` are told apart by the first word: a variable of the scope, or a global name, is a handle; any other name is a function. A function hidden that way is called with `call` and its handle awaited.
- The functions that wait for time and for descriptors stay functions that return a handle, so both forms work on them and nothing about them is special: `await asyncsleep 100`, or `call asyncsleep 100 as h` and later `await h` to wait for several things at once.
- A task that nobody awaits is two lines, `call conn c as k` and `call asyncdetach k`, 11 tokens. It occurs once in a server; a statement of its own is left out until programs show it paying.

With the statements in the compiler, the programs of `bench/async/` as they are run and measured ([performance](performance.md#async-2026-11-20)), comments left out:

| program | AIL | C |
|---|---:|---:|
| `timer`: two tasks that print at their own pace | 82 | |
| `echod`: a TCP echo server, a task a connection (`echo.aw` and `echod.aw`) | 259 | 503 |
| `ping`: load for it, with the time of every round trip and its distribution | 648 | |

The server in C (`echod.c`) is written on `epoll` by hand, as the AIL one would be without the library: it keeps no state for a connection and retries a write that finds the socket full in a loop, which the tasks do properly, by waiting. Of the 259 tokens, 133 are the two functions that serve (119 before they were indented), 48 the six `use` lines and 78 the reading of the port and the start. An AI that writes the server writes a loop around `await asyncread` and `await asyncwriteall`; what it does not write is the state of each connection and the table of who waits for what.
