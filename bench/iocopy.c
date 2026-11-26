/* copy the input to the output, 64 KiB a read: the cost of read and write */
#include <unistd.h>

static char b[65536];

int main(void)
{
    for (;;) {
        ssize_t n = read(0, b, sizeof b);
        char *p = b;
        if (n < 0)
            return 1;
        if (n == 0)
            return 0;
        while (n) {
            ssize_t w = write(1, p, n);
            if (w <= 0)
                return 1;
            p += w;
            n -= w;
        }
    }
}
