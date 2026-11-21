#include <unistd.h>

char in[4096];
char out[5];

int main(void)
{
    ssize_t n = read(0, in, 4096);
    if (n < 0)
        return 1;
    out[0] = '0' + n / 1000;
    out[1] = '0' + n / 100 % 10;
    out[2] = '0' + n / 10 % 10;
    out[3] = '0' + n % 10;
    out[4] = '\n';
    return write(1, out, 5) != 5;
}
