#include "algo/harness.h"

static u64 *place(const u64 *t, u64 m, u64 k)
{
    u64 h = k * 0x9e3779b97f4a7c15 >> 32 & m;
    while (t[h * 2] != 0 && t[h * 2] != k)
        h = (h + 1) & m;
    return (u64 *)t + h * 2;
}

static void insert(u64 *t, u64 m, const u64 *q, u64 n)
{
    for (u64 i = 0; i < n; ++i) {
        u64 *p = place(t, m, q[i]);
        p[0] = q[i];
        p[1] = i;
    }
}

static void lookup(const u64 *t, u64 m, const u64 *q, u64 n, u64 *r)
{
    for (u64 i = 0; i < n; ++i) {
        const u64 *p = place(t, m, q[i]);
        r[i] = p[0] ? p[1] : (u64)-1;
    }
}

int main(void)
{
    return algo_hash(insert, lookup, lookup);
}
