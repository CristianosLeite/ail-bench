#include <unistd.h>

int main(void)
{
    return write(1, "hello world\n", 12) != 12;
}
