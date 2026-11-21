#include "algo/harness.h"

static void bfs(const u64 *off, const u64 *nbr, u64 n, uint8_t *seen, u64 *r)
{
    u64 t = 0, h = 0;
    for (u64 s = 0; s < n; ++s) {
        if (seen[s])
            continue;
        seen[s] = 1;
        r[t++] = s;
        while (h < t) {
            u64 v = r[h++];
            for (u64 e = off[v]; e < off[v + 1]; ++e) {
                u64 w = nbr[e];
                if (!seen[w]) {
                    seen[w] = 1;
                    r[t++] = w;
                }
            }
        }
    }
}

static void dfs(const u64 *off, const u64 *nbr, u64 n, uint8_t *seen, u64 *st, u64 *r)
{
    u64 k = 0;
    for (u64 s = 0; s < n; ++s) {
        if (seen[s])
            continue;
        st[0] = s;
        for (u64 p = 1; p;) {
            u64 v = st[--p];
            if (seen[v])
                continue;
            seen[v] = 1;
            r[k++] = v;
            for (u64 e = off[v]; e < off[v + 1]; ++e)
                st[p++] = nbr[e];
        }
    }
}

int main(void)
{
    return algo_graph(bfs, dfs);
}
