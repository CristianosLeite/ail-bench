#include <stdint.h>
#include <unistd.h>

#define SIZE 16000000
static unsigned char s[SIZE];

int main(void)
{
    uint64_t n, c = 0;
    if (read(0, &n, sizeof n) != sizeof n || n > SIZE)
        return 1;
    for (uint64_t i = 2; i < n; ++i) {
        if (s[i])
            continue;
        ++c;
        for (uint64_t j = i * i; j < n; j += i)
            s[j] = 1;
    }
    return write(1, &c, sizeof c) != sizeof c;
}
