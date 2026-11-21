#include "algo/harness.h"

/* a vector that doubles its room, a ring of 1024 and a binary heap, each
   as a program in C has them: an array from malloc or realloc */
static void vec(const u64 *a, u64 n, u64 *r, void **st)
{
    u64 *v = 0, len = 0, room = 0;
    (void)st;
    for (u64 i = 0; i < n; ++i) {
        if (len == room) {
            room = room ? room * 2 : 512;
            v = realloc(v, room * 8);
            if (!v)
                exit(1);
        }
        v[len++] = a[i];
    }
    for (u64 i = 0; i < n; ++i)
        r[i] = v[--len];
    free(v);
}

static void queue(const u64 *a, u64 n, u64 *r, void **st)
{
    u64 *q = malloc(1024 * 8), head = 0, count = 0, j = 0;
    (void)st;
    if (!q)
        exit(1);
    for (u64 i = 0; i < n; ++i) {
        if (count == 1024) {
            r[j++] = q[head];
            head = (head + 1) & 1023;
            --count;
        }
        q[(head + count++) & 1023] = a[i];
    }
    while (j < n) {
        r[j++] = q[head];
        head = (head + 1) & 1023;
    }
    free(q);
}

static void heap(const u64 *a, u64 n, u64 *r, void **st)
{
    u64 *h = 0, len = 0, room = 0;
    (void)st;
    for (u64 i = 0; i < n; ++i) {
        if (len == room) {
            room = room ? room * 2 : 512;
            h = realloc(h, room * 8);
            if (!h)
                exit(1);
        }
        u64 x = a[i], c = len++;
        while (c) {
            u64 p = (c - 1) / 2;
            if (h[p] <= x)
                break;
            h[c] = h[p];
            c = p;
        }
        h[c] = x;
    }
    for (u64 i = 0; i < n; ++i) {
        r[i] = h[0];
        u64 x = h[--len], p = 0;
        for (;;) {
            u64 c = p * 2 + 1;
            if (c >= len)
                break;
            if (c + 1 < len && h[c + 1] < h[c])
                ++c;
            if (x <= h[c])
                break;
            h[p] = h[c];
            p = c;
        }
        if (len)
            h[p] = x;
    }
    free(h);
}

int main(void)
{
    return algo_coll(vec, queue, heap);
}
