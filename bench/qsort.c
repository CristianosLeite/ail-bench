/* The sort of the C library on the input of the sorting programs, for
   build/tools/perf --qsort: what a caller of the generic interface gets,
   a comparison through a pointer for every pair. */
#include "algo/harness.h"

static int cmp(const void *a, const void *b)
{
    u64 x = *(const u64 *)a, y = *(const u64 *)b;
    return (x > y) - (x < y);
}

static void sort(u64 *a, u64 n, u64 *t)
{
    (void)t;
    qsort(a, n, sizeof *a, cmp);
}

int main(void)
{
    return algo_sort(sort);
}
