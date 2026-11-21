/* What the algorithm programs of bench/ share; bench/algo/harness.aw
   says what it does, and this is the same statement for statement. The
   clock is clock_gettime of the C library, which calls the function of
   the vDSO that the harness in AIL finds and calls itself. */
#include <stdint.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
#include <unistd.h>

typedef uint64_t u64;

static u64 algo_in[5], algo_out[4], algo_rng;

static u64 algo_now(void)
{
    struct timespec t;
    clock_gettime(CLOCK_MONOTONIC, &t);
    return (u64)t.tv_sec * 1000000000u + (u64)t.tv_nsec;
}

static u64 algo_rnd(void)
{
    u64 z = algo_rng += 0x9e3779b97f4a7c15;
    z = (z ^ z >> 30) * 0xbf58476d1ce4e5b9;
    z = (z ^ z >> 27) * 0x94d049bb133111eb;
    return z ^ z >> 31;
}

static void algo_read(void)
{
    size_t n = 0;
    while (n < sizeof algo_in) {
        ssize_t r = read(0, (char *)algo_in + n, sizeof algo_in - n);
        if (r < 1)
            exit(1);
        n += (size_t)r;
    }
    algo_rng = algo_in[0];
    if (algo_in[1] >> 28)
        exit(1);
}

static u64 *algo_mem(u64 n)
{
    u64 *p = calloc(n ? n : 1, 8);
    if (!p)
        exit(1);
    return p;
}

static void algo_write(const void *p, u64 n)
{
    const char *c = p;
    while (n) {
        ssize_t r = write(1, c, n);
        if (r < 1)
            exit(1);
        c += r;
        n -= (u64)r;
    }
}

static void algo_copy(u64 *d, const u64 *s, u64 n)
{
    for (u64 i = 0; i < n; ++i)
        d[i] = s[i];
}

static int algo_end(const u64 *a, u64 n, const u64 *r, u64 l, u64 k)
{
    u64 h = 0;
    if (algo_in[4] == 1) {
        algo_write(a, n * 8);
        return 0;
    }
    if (algo_in[4] == 2) {
        algo_write(r, l * 8);
        return 0;
    }
    for (u64 i = 0; i < l; ++i)
        h = h * 31 + r[i];
    algo_out[0] = h;
    algo_write(algo_out, k * 8 + 8);
    return 0;
}

static void algo_fill(u64 *a, u64 n, u64 s)
{
    u64 x = 0;
    for (u64 i = 0; i < n; ++i) {
        u64 v = algo_rnd();
        if (s == 1)
            v = x += v & 0xffff;
        if (s == 2)
            v = x -= v & 0xffff;
        if (s == 3)
            v &= 15;
        a[i] = v;
    }
}

static inline int algo_sort(void (*k)(u64 *a, u64 n, u64 *t))
{
    algo_read();
    u64 n = algo_in[1], ns = 0;
    u64 *a = algo_mem(n * 3), *w = a + n, *t = w + n;
    algo_fill(a, n, algo_in[2]);
    algo_copy(w, a, n);
    for (u64 r = algo_in[3]; r; --r) {
        algo_copy(w, a, n);
        u64 b = algo_now();
        k(w, n, t);
        ns += algo_now() - b;
    }
    algo_out[1] = ns;
    return algo_end(a, n, w, n, 1);
}

static inline int algo_search(void (*k)(const u64 *a, u64 n, const u64 *q, u64 *w))
{
    algo_read();
    u64 n = algo_in[1], ns = 0, x = 0;
    u64 *a = algo_mem(n * 3), *q = a + n, *w = q + n;
    u64 mask = algo_in[2] == 3 ? 1 : 0xffff;
    for (u64 i = 0; i < n; ++i)
        a[i] = x += algo_rnd() & mask;
    for (u64 i = 0; i < n; ++i)
        q[i] = algo_rnd() % (x + 2);
    for (u64 r = algo_in[3]; r; --r) {
        u64 b = algo_now();
        k(a, n, q, w);
        ns += algo_now() - b;
    }
    algo_out[1] = ns;
    return algo_end(a, n * 2, w, n, 1);
}

static inline int algo_hash(void (*ki)(u64 *t, u64 mask, const u64 *q, u64 n),
                            void (*kh)(const u64 *t, u64 mask, const u64 *q, u64 n, u64 *w),
                            void (*km)(const u64 *t, u64 mask, const u64 *q, u64 n, u64 *w))
{
    algo_read();
    u64 n = algo_in[1], c = 16, x = 0, y = 0, z = 0;
    while (c < n * 2)
        c *= 2;
    u64 *a = algo_mem(n * 4 + c * 2), *q = a + n, *w = q + n, *t = w + n * 2;
    for (u64 i = 0; i < n; ++i) {
        u64 v = algo_rnd();
        if (algo_in[2] == 3)
            v &= 255;
        a[i] = v | 1;
    }
    for (u64 i = 0; i < n; ++i)
        q[i] = (algo_rnd() | 1) << 1;
    for (u64 r = algo_in[3]; r; --r) {
        for (u64 i = 0; i < c * 2; ++i)
            t[i] = 0;
        u64 b = algo_now();
        ki(t, c - 1, a, n);
        x += algo_now() - b;
        b = algo_now();
        kh(t, c - 1, a, n, w);
        y += algo_now() - b;
        b = algo_now();
        km(t, c - 1, q, n, w + n);
        z += algo_now() - b;
    }
    algo_out[1] = x;
    algo_out[2] = y;
    algo_out[3] = z;
    return algo_end(a, n * 2, w, n * 2, 3);
}

static inline int algo_graph(void (*kb)(const u64 *off, const u64 *nbr, u64 n, uint8_t *seen, u64 *w),
                             void (*kd)(const u64 *off, const u64 *nbr, u64 n, uint8_t *seen, u64 *t, u64 *w))
{
    algo_read();
    u64 n = algo_in[1], x = 0, y = 0, h = 0;
    u64 mask = algo_in[2] == 3 ? 1 : 15;
    u64 *a = algo_mem(n * 35 + 4), *q = a + n + 1;
    for (u64 i = 0; i < n; ++i) {
        a[i] = x;
        for (u64 v = algo_rnd() & mask; v; --v)
            q[x++] = algo_rnd() % n;
    }
    a[n] = x;
    u64 *w = q + x, *t = w + n * 2;
    uint8_t *z = (uint8_t *)(t + x + 1);
    for (u64 r = algo_in[3]; r; --r) {
        for (u64 i = 0; i < n; ++i)
            z[i] = 0;
        u64 b = algo_now();
        kb(a, q, n, z, w);
        y += algo_now() - b;
        for (u64 i = 0; i < n; ++i)
            z[i] = 0;
        b = algo_now();
        kd(a, q, n, z, t, w + n);
        h += algo_now() - b;
    }
    algo_out[1] = y;
    algo_out[2] = h;
    return algo_end(a, n + 1 + x, w, n * 2, 2);
}

typedef void (*algo_kern)(const double *a, const double *b, double *r, u64 n);

static inline int algo_num(algo_kern ka, algo_kern kb, algo_kern kc, int sq)
{
    algo_read();
    algo_kern ks[3] = {ka, kb, kc};
    u64 n = algo_in[1], l = n, k = (ka != 0) + (kb != 0) + (kc != 0);
    if (sq) {
        l = n * n;
        if (l >> 28)
            exit(1);
    }
    u64 *a = algo_mem(l * (2 + k)), *w = a + l * 2;
    double *x = calloc(l * 2 ? l * 2 : 1, 8), *r = calloc(l * k ? l * k : 1, 8);
    if (!x || !r)
        exit(1);
    algo_fill(a, l * 2, algo_in[2]);
    for (u64 i = 0; i < l * 2; ++i)
        x[i] = (double)(int64_t)(a[i] & 0xfffff) / 1024 - 512;
    memcpy(a, x, l * 16);
    for (u64 t = algo_in[3]; t; --t)
        for (u64 i = 0; i < k; ++i) {
            u64 b = algo_now();
            ks[i](x, x + l, r + i * l, n);
            algo_out[1 + i] += algo_now() - b;
        }
    memcpy(w, r, l * k * 8);
    return algo_end(a, l * 2, w, l * k, k);
}

typedef void (*algo_ckern)(const u64 *a, u64 n, u64 *r, void **st);

static inline int algo_coll(algo_ckern ka, algo_ckern kb, algo_ckern kc)
{
    algo_read();
    algo_ckern ks[3] = {ka, kb, kc};
    u64 n = algo_in[1], k = (ka != 0) + (kb != 0) + (kc != 0);
    u64 *a = algo_mem(n * (1 + k)), *w = a + n;
    void *st[8];
    algo_fill(a, n, algo_in[2]);
    for (u64 t = algo_in[3]; t; --t) {
        memset(st, 0, sizeof st);
        for (u64 i = 0; i < k; ++i) {
            u64 b = algo_now();
            ks[i](a, n, w + i * n, st);
            algo_out[1 + i] += algo_now() - b;
        }
    }
    return algo_end(a, n, w, n * k, k);
}
