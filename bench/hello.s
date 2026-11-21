.intel_syntax noprefix
.globl _start
_start:
    mov eax, 1
    mov edi, 1
    lea rsi, [rip+msg]
    mov edx, 12
    syscall
    lea rdi, [rax-12]
    mov eax, 60
    syscall
msg: .ascii "hello world\n"
