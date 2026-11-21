/* bench/async/echod.c -- what bench/async/echod.aw is measured against:
   the same server written on epoll by hand, one thread, level-triggered,
   a read and a write for each event.  echod [port]; prints the port. */
#define _GNU_SOURCE
#include <arpa/inet.h>
#include <errno.h>
#include <fcntl.h>
#include <stdio.h>
#include <stdlib.h>
#include <sys/epoll.h>
#include <sys/socket.h>
#include <unistd.h>

int main(int argc, char **argv) {
    int port = argc > 1 ? atoi(argv[1]) : 8080, one = 1;
    int l = socket(AF_INET, SOCK_STREAM | SOCK_NONBLOCK, 0);
    struct sockaddr_in a = {.sin_family = AF_INET, .sin_port = htons(port)};
    socklen_t n = sizeof a;
    setsockopt(l, SOL_SOCKET, SO_REUSEADDR, &one, sizeof one);
    if (bind(l, (struct sockaddr *)&a, sizeof a) || listen(l, 4096)) return 1;
    getsockname(l, (struct sockaddr *)&a, &n);
    printf("%d\n", ntohs(a.sin_port));
    fflush(stdout);
    int ep = epoll_create1(0);
    struct epoll_event ev = {.events = EPOLLIN, .data.fd = l}, evs[64];
    epoll_ctl(ep, EPOLL_CTL_ADD, l, &ev);
    char b[4096];
    for (;;) {
        int k = epoll_wait(ep, evs, 64, -1);
        for (int i = 0; i < k; i++) {
            int fd = evs[i].data.fd;
            if (fd == l) {
                int c;
                while ((c = accept4(l, 0, 0, SOCK_NONBLOCK)) >= 0) {
                    ev.events = EPOLLIN;
                    ev.data.fd = c;
                    epoll_ctl(ep, EPOLL_CTL_ADD, c, &ev);
                }
                continue;
            }
            ssize_t r = read(fd, b, sizeof b);
            if (r <= 0) { close(fd); continue; }
            for (ssize_t w = 0; w < r;) {
                /* no SIGPIPE for a client that is gone; a full socket is
                   tried again, as a benchmark may, anything else closes */
                ssize_t m = send(fd, b + w, r - w, MSG_NOSIGNAL);
                if (m >= 0) { w += m; continue; }
                if (errno == EAGAIN || errno == EINTR) continue;
                close(fd);
                break;
            }
        }
    }
}
