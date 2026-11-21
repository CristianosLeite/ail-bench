.intel_syntax noprefix
.globl _start
.bss
b: .skip 4096
.text
_start:
    xor eax, eax
    xor edi, edi
    lea rsi, [rip+b]
    mov edx, 4096
    syscall
    cmp rax, 4096
    ja fail
    mov rbx, rax
    mov eax, 1
    mov edi, 1
    mov rdx, rbx
    syscall
    cmp rax, rbx
    jne fail
    xor edi, edi
    jmp done
fail:
    mov edi, 1
done:
    mov eax, 60
    syscall
