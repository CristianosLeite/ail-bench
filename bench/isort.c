#include <stdint.h>
#include <unistd.h>

#define COUNT 30000
static uint64_t a[COUNT];

int main(void)
{
    uint64_t x, h = 0;
    if (read(0, &x, sizeof x) != sizeof x)
        return 1;
    for (int i = 0; i < COUNT; ++i) {
        x = x * 0x5851f42d4c957f2d + 0x14057b7ef767814f;
        a[i] = x >> 33;
    }
    for (int i = 1; i < COUNT; ++i) {
        uint64_t k = a[i];
        int j = i;
        for (; j > 0 && a[j - 1] > k; --j)
            a[j] = a[j - 1];
        a[j] = k;
    }
    for (int i = 0; i < COUNT; ++i)
        h = h * 31 + a[i];
    return write(1, &h, sizeof h) != sizeof h;
}
