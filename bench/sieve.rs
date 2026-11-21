use std::io::{Read, Write};

fn main() -> std::io::Result<()> {
    let mut bytes = [0_u8; 8];
    std::io::stdin().read_exact(&mut bytes)?;
    let n = u64::from_le_bytes(bytes);
    if n > 16000000 {
        std::process::exit(1);
    }
    let n = n as usize;
    let mut s = vec![0_u8; n];
    let mut c = 0_u64;
    for i in 2..n {
        if s[i] != 0 {
            continue;
        }
        c += 1;
        let mut j = i * i;
        while j < n {
            s[j] = 1;
            j += i;
        }
    }
    std::io::stdout().write_all(&c.to_le_bytes())
}
