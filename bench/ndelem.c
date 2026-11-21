#include "algo/harness.h"

static void add(const double *a, const double *b, double *r, u64 n)
{
    for (u64 i = 0; i < n; ++i)
        r[i] = a[i] + b[i];
}

static void mul(const double *a, const double *b, double *r, u64 n)
{
    for (u64 i = 0; i < n; ++i)
        r[i] = a[i] * b[i];
}

static void scale(const double *a, const double *b, double *r, u64 n)
{
    if (n) {
        double k = b[0];
        for (u64 i = 0; i < n; ++i)
            r[i] = a[i] * k;
    }
}

int main(void)
{
    return algo_num(add, mul, scale, 0);
}
