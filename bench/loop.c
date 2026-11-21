#include <unistd.h>

int main(void)
{
    char out[2] = {0, '\n'};
    for (int i = 0; i < 10; i++) {
        out[0] = '0' + i;
        write(1, out, 2);
    }
    return 0;
}
