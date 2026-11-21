#include "algo/harness.h"

static void mergesort(u64 *a, u64 n, u64 *t)
{
    u64 *s = a, *d = t;
    for (u64 w = 1; w < n; w *= 2) {
        for (u64 o = 0; o < n;) {
            u64 m = o + w, h;
            if (m > n)
                m = n;
            h = m + w;
            if (h > n)
                h = n;
            u64 *i = s + o, *p = s + m, *j = p, *q = s + h, *k = d + o;
            while (i < p && j < q) {
                if (*j < *i)
                    *k++ = *j++;
                else
                    *k++ = *i++;
            }
            while (i < p)
                *k++ = *i++;
            while (j < q)
                *k++ = *j++;
            o = h;
        }
        u64 *x = s;
        s = d;
        d = x;
    }
    if (s != a)
        for (u64 i = 0; i < n; ++i)
            a[i] = s[i];
}

int main(void)
{
    return algo_sort(mergesort);
}
