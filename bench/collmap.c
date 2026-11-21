#include "algo/harness.h"

/* a hash map with open addressing and linear probing, as lib/std/map.aw has
   it: places of a state (0 free, 1 a key, 2 taken out), a key and a
   value, a power of two of them, moved to a new table when three
   quarters hold or held a key */
struct place { u64 state, key, value; };
struct map { struct place *t; u64 places, keys, used; };

static u64 hash(u64 k)
{
    k = (k ^ k >> 30) * 0xbf58476d1ce4e5b9;
    k = (k ^ k >> 27) * 0x94d049bb133111eb;
    return k ^ k >> 31;
}

/* the place of the key, or the place where it goes */
static struct place *find(const struct map *m, u64 k, int *there)
{
    struct place *gone = 0;
    *there = 0;
    if (!m->places)
        return 0;
    for (u64 h = hash(k) & (m->places - 1);; h = (h + 1) & (m->places - 1)) {
        struct place *p = m->t + h;
        if (p->state == 0)
            return gone ? gone : p;
        if (p->state == 2) {
            if (!gone)
                gone = p;
        } else if (p->key == k) {
            *there = 1;
            return p;
        }
    }
}

static void grow(struct map *m)
{
    struct map n = {0, m->places ? m->places : 64, 0, 0};
    int there;
    if (m->keys * 2 >= n.places)
        n.places *= 2;
    n.t = calloc(n.places, sizeof *n.t);
    if (!n.t)
        exit(1);
    for (u64 i = 0; i < m->places; ++i)
        if (m->t[i].state == 1) {
            struct place *p = find(&n, m->t[i].key, &there);
            p->state = 1;
            p->key = m->t[i].key;
            p->value = m->t[i].value;
            ++n.keys;
            ++n.used;
        }
    free(m->t);
    *m = n;
}

static void put(struct map *m, u64 k, u64 v)
{
    int there;
    struct place *p = find(m, k, &there);
    if (!there) {
        if (!p || (p->state == 0 && (m->used + 1) * 4 > m->places * 3)) {
            grow(m);
            p = find(m, k, &there);
        }
        if (p->state == 0)
            ++m->used;
        p->state = 1;
        p->key = k;
        ++m->keys;
    }
    p->value = v;
}

static void kput(const u64 *a, u64 n, u64 *r, void **st)
{
    struct map *m = calloc(1, sizeof *m);
    if (!m)
        exit(1);
    st[0] = m;
    for (u64 i = 0; i < n; ++i)
        put(m, a[i], i);
    if (n)
        r[0] = m->keys;
}

static void kget(const u64 *a, u64 n, u64 *r, void **st)
{
    const struct map *m = st[0];
    int there;
    for (u64 i = 0; i < n; ++i) {
        const struct place *p = find(m, a[i], &there);
        r[i] = there ? p->value : 0;
    }
}

static void kdel(const u64 *a, u64 n, u64 *r, void **st)
{
    struct map *m = st[0];
    int there;
    for (u64 i = 0; i < n; ++i) {
        struct place *p = find(m, a[i], &there);
        if (there) {
            p->state = 2;
            --m->keys;
        }
        r[i] = (u64)there;
    }
    free(m->t);
    free(m);
}

int main(void)
{
    return algo_coll(kput, kget, kdel);
}
