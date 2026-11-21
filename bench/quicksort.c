#include "algo/harness.h"

static void quicksort(u64 *p, u64 *q)
{
    while (q - p > 1) {
        u64 a = p[0], b = p[(q - p) / 2], c = q[-1];
        if (a > b) {
            u64 x = a;
            a = b;
            b = x;
        }
        if (b > c) {
            b = c;
            if (a > b)
                b = a;
        }
        u64 *l = p, *i = p, *g = q;
        while (i < g) {
            u64 v = *i;
            if (v < b) {
                *i++ = *l;
                *l++ = v;
            } else if (v > b) {
                *i = *--g;
                *g = v;
            } else {
                ++i;
            }
        }
        if (l - p < q - g) {
            quicksort(p, l);
            p = g;
        } else {
            quicksort(g, q);
            q = l;
        }
    }
}

static void sort(u64 *a, u64 n, u64 *t)
{
    (void)t;
    quicksort(a, a + n);
}

int main(void)
{
    return algo_sort(sort);
}
