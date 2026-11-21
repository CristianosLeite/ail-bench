#include "algo/harness.h"

static u64 lower(const u64 *a, u64 n, u64 k)
{
    u64 l = 0;
    while (l < n) {
        u64 m = (l + n) >> 1;
        if (a[m] < k)
            l = m + 1;
        else
            n = m;
    }
    return l;
}

static void search(const u64 *a, u64 n, const u64 *q, u64 *r)
{
    for (u64 i = 0; i < n; ++i)
        r[i] = lower(a, n, q[i]);
}

int main(void)
{
    return algo_search(search);
}
