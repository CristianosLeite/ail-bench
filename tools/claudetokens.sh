#!/bin/sh
# Count the benchmark programs with Claude's tokenizers, beside the
# counts of build/tools/tokens (o200k_base), and print both as Markdown.
# Usage: tools/claudetokens.sh [name ...]   (default: every example)
#
# There is no file of Claude's tokens to count with here: the counts come
# from Anthropic's token counting, asked through claudetokenizer.com, a
# site of a third party that passes a text on with a key of its own (and,
# for its own columns, to Google's count as well). SO THIS SENDS THE CODE
# OF EVERY PROGRAM OUT, which tools/tokens.sh never does. Run it by hand;
# nothing else runs it. Needs curl and the network.
#
# For each source the text sent is what build/tools/tokens counts, the
# file without comments and blank lines (tokens --code). One request a
# file, two seconds apart, and each answer is kept in build/claudetokens/
# so that a second run asks only for what is missing or has changed. An
# answer has the count of two models, the one named in MODEL and the one
# in OLD, whose tokenizers differ, and that of o200k_base, which must be
# the count of build/tools/tokens: that is the check that the text
# arrived as it was sent.
#
# A count is that of a whole message, so what the message costs without
# its text is taken off: it is measured first for each model, as twice
# the count of a line less the count of that line twice. The last line
# end of a text is not counted by the service, so a count may be one
# token short of the text's with its last line end, in every language
# alike.
set -eu
cd "$(dirname "$0")/.."
URL=${URL:-https://www.claudetokenizer.com/api}
MODEL=${MODEL:-claude-fable-5}
OLD=${OLD:-claude-sonnet-4-6}
tools/pin.sh o200k_base.tiktoken UnicodeData.txt
make -s build/tools/tokens > /dev/null
dir=build/claudetokens
want=$*
mkdir -p "$dir"

# ask file: the three counts of the text in the file, "model old o200k"
ask() {
    {
        printf '{"model":"%s","comparisonModel":"%s","text":"' "$MODEL" "$OLD"
        # the text as a JSON string; bytes above 127 are UTF-8 and stay
        LC_ALL=C awk 'BEGIN {
            for (i = 1; i < 32; i++) esc[sprintf("%c", i)] = sprintf("\\u%04x", i)
            esc["\t"] = "\\t"; esc["\""] = "\\\""; esc["\\"] = "\\\\"
        } {
            s = ""
            for (i = 1; i <= length($0); i++) {
                c = substr($0, i, 1)
                s = s (c in esc ? esc[c] : c)
            }
            printf "%s\\n", s
        }' "$1"
        printf '"}'
    } > "$dir/request"
    curl -sS -m 60 -X POST "$URL" -H 'Content-Type: application/json' \
        --data-binary "@$dir/request" > "$dir/answer" || return 1
    a=$(sed -n 's/.*"input_tokens":\([0-9]*\).*/\1/p' "$dir/answer")
    b=$(sed -n 's/.*"comparisonTokens":\([0-9]*\).*/\1/p' "$dir/answer")
    c=$(sed -n 's/.*"gpt4oTokens":\([0-9]*\).*/\1/p' "$dir/answer")
    if [ -z "$a" ] || [ -z "$b" ] || [ -z "$c" ]; then
        echo "claudetokens: no counts in the answer: $(head -c 300 "$dir/answer")" >&2
        return 1
    fi
    echo "$a $b $c"
    sleep 2
}

# what a message costs without its text, for each model
printf 'x=a+b\n' > "$dir/one"
printf 'x=a+b\nx=a+b\n' > "$dir/two"
set -- $(ask "$dir/one")
a1=$1 b1=$2
set -- $(ask "$dir/two")
wa=$((a1 * 2 - $1)) wb=$((b1 * 2 - $2))

names=${want:-$(ls ail/examples/*.aiw bench/*.aiw | sed 's|.*/||; s|\.aiw$||')}
: > "$dir/rows"
for n in $names; do
    ail=bench/$n.aiw
    [ -f "$ail" ] || ail=ail/examples/$n.aiw
    for l in "AIL $ail #" "C bench/$n.c //" "Rust bench/$n.rs //" "asm bench/$n.s #"; do
        set -- $l
        [ -f "$2" ] || continue
        build/tools/tokens --code "$3" "$2" > "$dir/text"
        sum=$(sha256sum < "$dir/text" | cut -c1-64)
        key="$dir/$(echo "$2" | tr / _).$MODEL.$OLD"
        if [ -f "$key" ] && [ "$(sed -n 1p "$key")" = "$sum" ]; then
            counts=$(sed -n 2p "$key")
        else
            counts=$(ask "$dir/text")
            printf '%s\n%s\n' "$sum" "$counts" > "$key"
        fi
        own=$(build/tools/tokens -c "$3" "$2" | cut -d' ' -f1)
        echo "$n $1 $own $counts" >> "$dir/rows"
    done
done

awk -v wa="$wa" -v wb="$wb" -v model="$MODEL" -v old="$OLD" '
{
    n = $1; l = $2; own = $3; a = $4 - wa; b = $5 - wb
    if ($6 != own) { printf "claudetokens: %s in %s: o200k_base is %d here and %d there\n", n, l, own, $6 > "/dev/stderr"; bad = 1 }
    if (!(n in seen)) { seen[n] = 1; order[++nn] = n }
    t[l, n, 0] = own; t[l, n, 1] = a; t[l, n, 2] = b; has[l, n] = 1
}
END {
    if (bad) exit 1
    split("AIL C Rust asm", L, " ")
    title[0] = "o200k_base (build/tools/tokens)"; title[1] = model; title[2] = old
    for (k = 0; k < 3; k++) {
        printf "\n**%s**\n\n| Program | AIL | C | Rust | asm |\n|---|---:|---:|---:|---:|\n", title[k]
        for (i = 1; i <= 4; i++) { tot[i] = 0; base[i] = 0 }
        for (j = 1; j <= nn; j++) {
            n = order[j]
            printf "| %s |", n
            for (i = 1; i <= 4; i++) {
                if (has[L[i], n]) {
                    printf " %d |", t[L[i], n, k]
                    tot[i] += t[L[i], n, k]; base[i] += t["AIL", n, k]
                } else printf " |"
            }
            printf "\n"
        }
        printf "| **Total** |"
        for (i = 1; i <= 4; i++) printf " **%d** |", tot[i]
        printf "\n| relative to AIL |"
        for (i = 1; i <= 4; i++) printf " %.2f |", base[i] ? tot[i] / base[i] : 0
        printf "\n"
    }
    printf "\nA message without its text: %d tokens in %s, %d in %s.\n", wa, model, wb, old
}' "$dir/rows"
