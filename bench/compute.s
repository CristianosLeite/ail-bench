.intel_syntax noprefix
.globl _start
.bss
buffer: .skip 8
.text
_start:
    xor eax, eax
    xor edi, edi
    lea rsi, [rip + buffer]
    mov edx, 8
    syscall
    cmp rax, 8
    jne fail
    mov rax, [rsi]
    mov ecx, 100000000
step:
    mov rdx, rax
    shr rdx, 13
    xor rax, rdx
    imul rax, rax, 1664525
    add rax, 1013904223
    dec ecx
    jnz step
    mov [rsi], rax
    mov eax, 1
    mov edi, 1
    mov edx, 8
    syscall
    cmp rax, 8
    jne fail
    xor edi, edi
    jmp done
fail:
    mov edi, 1
done:
    mov eax, 60
    syscall
