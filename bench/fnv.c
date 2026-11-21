#include <stdint.h>
#include <unistd.h>

#define SIZE 1048576
static unsigned char d[SIZE];

int main(void)
{
    size_t n = 0;
    ssize_t r;
    while ((r = read(0, d + n, SIZE - n)) > 0)
        n += r;
    if (r < 0)
        return 1;
    uint64_t h = 0xcbf29ce484222325;
    for (int k = 0; k < 100; ++k)
        for (size_t i = 0; i < n; ++i)
            h = (h ^ d[i]) * 0x100000001b3;
    return write(1, &h, sizeof h) != sizeof h;
}
