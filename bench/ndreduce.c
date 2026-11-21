#include "algo/harness.h"

static void total(const double *a, const double *b, double *r, u64 n)
{
    (void)b;
    if (n) {
        double s = 0;
        for (u64 i = 0; i < n * n; ++i)
            s += a[i];
        r[0] = s;
    }
}

static void cols(const double *a, const double *b, double *r, u64 n)
{
    (void)b;
    for (u64 j = 0; j < n; ++j)
        r[j] = 0;
    for (u64 i = 0; i < n; ++i)
        for (u64 j = 0; j < n; ++j)
            r[j] += a[i * n + j];
}

static void dot(const double *a, const double *b, double *r, u64 n)
{
    if (n) {
        double s = 0;
        for (u64 i = 0; i < n * n; ++i)
            s += a[i] * b[i];
        r[0] = s;
    }
}

int main(void)
{
    return algo_num(total, cols, dot, 1);
}
