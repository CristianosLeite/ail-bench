.intel_syntax noprefix
.globl _start
.bss
out: .skip 2
.text
_start:
    lea rsi, [rip+out]
    mov byte ptr [rsi+1], 10
    xor ebx, ebx
top:
    lea eax, [rbx+48]
    mov [rsi], al
    mov eax, 1
    mov edi, 1
    mov edx, 2
    syscall
    inc ebx
    cmp ebx, 10
    jb top
    mov eax, 60
    xor edi, edi
    syscall
