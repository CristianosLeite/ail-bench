#!/bin/sh
# Print the token cost of the benchmark programs as a Markdown table.
# Usage: tools/tokens.sh [name ...]
# Counts with build/tools/tokens (tools/tokens.aw), the encoding
# o200k_base. The two files it reads are pinned in tools/pins/MANIFEST
# and fetched on first use, which needs network access once.
set -eu
cd "$(dirname "$0")/.."
tools/pin.sh o200k_base.tiktoken UnicodeData.txt
make -s build/tools/tokens > /dev/null
exec build/tools/tokens "$@"
