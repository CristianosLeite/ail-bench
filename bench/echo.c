#include <unistd.h>

char b[4096];

int main(void)
{
    ssize_t n = read(0, b, 4096);
    if (n < 0)
        return 1;
    return write(1, b, n) != n;
}
