.intel_syntax noprefix
.globl _start
fact:
    test rdi, rdi
    jz one
    push rdi
    dec rdi
    call fact
    pop rdi
    imul rax, rdi
    ret
one:
    mov eax, 1
    ret
_start:
    mov edi, 5
    call fact
    mov rdi, rax
    mov eax, 60
    syscall
