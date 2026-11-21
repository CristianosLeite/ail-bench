#include "algo/harness.h"

static void sift(u64 *a, u64 i, u64 n, u64 v)
{
    for (u64 c = i * 2 + 1; c < n; c = i * 2 + 1) {
        u64 x = a[c];
        if (c + 1 < n && a[c + 1] > x)
            x = a[++c];
        if (x <= v)
            break;
        a[i] = x;
        i = c;
    }
    a[i] = v;
}

static void heapsort(u64 *a, u64 n, u64 *t)
{
    (void)t;
    for (u64 i = n >> 1; i;) {
        --i;
        sift(a, i, n, a[i]);
    }
    while (n > 1) {
        u64 v = a[--n];
        a[n] = a[0];
        sift(a, 0, n, v);
    }
}

int main(void)
{
    return algo_sort(heapsort);
}
