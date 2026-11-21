#include <stdint.h>
#include <unistd.h>

int main(void)
{
    uint64_t x;
    if (read(0, &x, sizeof x) != sizeof x)
        return 1;
    for (unsigned i = 0; i < 100000000; ++i) {
        x ^= x >> 13;
        x = x * 1664525 + 1013904223;
    }
    return write(1, &x, sizeof x) != sizeof x;
}
