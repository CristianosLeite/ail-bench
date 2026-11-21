#include <stdint.h>
#include <unistd.h>

int main(void)
{
    uint64_t n, c = 0;
    if (read(0, &n, sizeof n) != sizeof n || n > 10000000)
        return 1;
    for (; n; --n)
        for (uint64_t x = n; x != 1; ++c)
            x = x % 2 ? x * 3 + 1 : x / 2;
    return write(1, &c, sizeof c) != sizeof c;
}
