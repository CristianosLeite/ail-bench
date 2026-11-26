/* a million writes of 16 bytes: the cost of a call that is one system call */
#include <unistd.h>

int main(void)
{
    for (int i = 0; i < 1000000; ++i)
        if (write(1, "0123456789abcde\n", 16) != 16)
            return 1;
    return 0;
}
