.intel_syntax noprefix
.globl _start
.bss
in: .skip 4096
out: .skip 8
.text
_start:
    xor eax, eax
    xor edi, edi
    lea rsi, [rip+in]
    mov edx, 4096
    syscall
    cmp rax, 4096
    ja fail
    lea rsi, [rip+out]
    mov byte ptr [rsi+4], 10
    mov ecx, 3
    mov ebx, 10
digit:
    xor edx, edx
    div rbx
    add dl, 48
    mov [rsi+rcx], dl
    dec ecx
    jns digit
    mov eax, 1
    mov edi, 1
    mov edx, 5
    syscall
    cmp rax, 5
    jne fail
    xor edi, edi
    jmp done
fail:
    mov edi, 1
done:
    mov eax, 60
    syscall
