#!/bin/sh
# usage: tests/run.sh [name...]     (no names = every test)
# The compiler is build/ailc, or the one named by the environment variable AILC.
#
# A test is a set of files named tests/<name>.*:
#   .aw     the program (or .aw.sh, a script that prints the program)
#   .out    expected stdout of the compiled program
#   .code   expected exit status of the compiled program (default 0)
#   .err    expected compiler stderr; the compiler must then exit 1
# A test has either .err (compiling must fail) or .out (program must run).
# A compiler or program still running after $limit seconds is killed and fails its test,
# whatever status the test expects: the status is taken from a file that is
# only written when the program ends by itself. The kill is SIGKILL to the
# whole process group, so a program that ignores SIGTERM does not live on.
# Every run works in a directory of its own, removed when it ends, so runs
# at the same time do not overwrite each other's files.
cd "$(dirname "$0")/.." || exit 1
ailc=${AILC:-build/ailc}
limit=10
mkdir -p build/tests
out=$(mktemp -d build/tests/run.XXXXXX) || exit 1
trap 'rm -rf "$out"' EXIT
trap 'exit 1' HUP INT TERM
[ $# -eq 0 ] && set -- $(ls tests | sed -n 's/\.aw\(\.sh\)\{0,1\}$//p' | sort -u)

check() {
    if [ -f tests/$1.aw.sh ]
    then sh tests/$1.aw.sh > $out/$1.aw || return 1
    else cp tests/$1.aw $out/$1.aw || return 1
    fi
    # the compiler has the same deadline; killed, its status is neither 0 nor 1
    timeout --signal=KILL $limit $ailc < $out/$1.aw > $out/$1 2> $out/$1.err
    rc=$?
    if [ -f tests/$1.err ]; then
        [ $rc -eq 1 ] && cmp -s $out/$1.err tests/$1.err
        return
    fi
    [ $rc -eq 0 ] && [ ! -s $out/$1.err ] || return 1
    chmod +x $out/$1
    # timeout leads a process group of its own, whose id the wrapper sees
    # as $PPID. Not every timeout signals the group, so do it here as well.
    if ! timeout --signal=KILL $limit sh -c \
        'echo $PPID > "$1.pg"; "$1" > "$1.got"; echo $? > "$1.rc"' sh $out/$1 \
        2> /dev/null            # a program killed by a signal makes sh report it
    then
        kill -s KILL -- -$(cat $out/$1.pg) 2> /dev/null
        return 1
    fi
    [ -f $out/$1.rc ] || return 1
    rc=$(cat $out/$1.rc)
    want=0
    [ -f tests/$1.code ] && want=$(cat tests/$1.code)
    [ $rc -eq "$want" ] && cmp -s $out/$1.got tests/$1.out
}

fail=0
for t; do
    if check $t
    then echo "ok   $t"
    else echo "FAIL $t"; fail=1
    fi
done
exit $fail
