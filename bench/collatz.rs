use std::io::{Read, Write};

fn main() -> std::io::Result<()> {
    let mut bytes = [0_u8; 8];
    std::io::stdin().read_exact(&mut bytes)?;
    let n = u64::from_le_bytes(bytes);
    if n > 10000000 {
        std::process::exit(1);
    }
    let mut c = 0_u64;
    for start in 1..=n {
        let mut x = start;
        while x != 1 {
            x = if x % 2 != 0 { x * 3 + 1 } else { x / 2 };
            c += 1;
        }
    }
    std::io::stdout().write_all(&c.to_le_bytes())
}
