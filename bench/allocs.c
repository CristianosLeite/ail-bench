/* 200000 blocks of 16 to 1039 bytes of zeros, 64 of them alive at a time,
 * each written at both ends: the cost of calloc and free */
#include <stdint.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

int main(void)
{
    static char *live[64];
    uint64_t x, s = 0;
    if (read(0, &x, sizeof x) != sizeof x)
        return 1;
    for (uint64_t i = 0; i < 200000; ++i) {
        uint64_t n = 16 + (x >> 33 & 1023), a, z;
        char *p = calloc(1, n);
        if (!p)
            return 1;
        memcpy(&a, p, 8);
        memcpy(&z, p + n - 8, 8);
        s += a + z + n;
        memcpy(p, &x, 8);
        memcpy(p + n - 8, &i, 8);
        char **q = &live[i & 63];
        if (*q) {
            memcpy(&a, *q, 8);
            s ^= a;
            free(*q);
        }
        *q = p;
        x = x * 6364136223846793005u + 1442695040888963407u;
    }
    for (int i = 0; i < 64; ++i)
        free(live[i]);
    return write(1, &s, sizeof s) != sizeof s;
}
