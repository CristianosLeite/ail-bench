#include "algo/harness.h"

static void matmul(const double *a, const double *b, double *r, u64 n)
{
    for (u64 i = 0; i < n * n; ++i)
        r[i] = 0;
    for (u64 i = 0; i < n; ++i)
        for (u64 k = 0; k < n; ++k) {
            double x = a[i * n + k];
            for (u64 j = 0; j < n; ++j)
                r[i * n + j] += x * b[k * n + j];
        }
}

int main(void)
{
    return algo_num(matmul, 0, 0, 1);
}
