#!/bin/sh
# usage: tools/pin.sh <name> ...
#
# Make sure the files named are at tools/pins/<name>: tools/pins/MANIFEST
# has the SHA-256 and the address of each. A file that is not there is
# fetched with curl. A copy whose checksum is not the manifest's is
# refused, and a fetched one is then removed; it is written through a
# temporary file, so a failure leaves no file that looks complete.
set -u
cd "$(dirname "$0")/.." || exit 1
[ $# -gt 0 ] || { echo "usage: tools/pin.sh <name> ..." >&2; exit 1; }

sum_ok() {
    [ "$(sha256sum < "$1" | cut -d' ' -f1)" = "$sum" ]
}

for name; do
    set -- $(grep -v -e '^#' -e '^[[:space:]]*$' tools/pins/MANIFEST | awk -v n="$name" '$1 == n')
    [ $# -eq 3 ] || { echo "pin: tools/pins/MANIFEST has no line for $name" >&2; exit 1; }
    sum=$2 url=$3
    file=tools/pins/$name
    if [ ! -e "$file" ]; then
        tmp=$(mktemp "$file.tmp.XXXXXX") || exit 1
        trap 'rm -f "$tmp"' EXIT
        trap 'exit 1' HUP INT TERM
        curl -fsSL -o "$tmp" "$url" 2> /dev/null || {
            echo "pin: cannot fetch $url" >&2
            echo "pin: put the file at $file" >&2
            exit 1
        }
        sum_ok "$tmp" || { echo "pin: $url: wrong checksum" >&2; exit 1; }
        chmod 644 "$tmp" && mv "$tmp" "$file" || exit 1
        trap - EXIT
    fi
    sum_ok "$file" || { echo "pin: $file: wrong checksum" >&2; exit 1; }
done
