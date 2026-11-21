use std::io::{Read, Write};

fn main() -> std::io::Result<()> {
    let mut bytes = [0_u8; 8];
    std::io::stdin().read_exact(&mut bytes)?;
    let mut x = u64::from_le_bytes(bytes);
    for _ in 0..100_000_000 {
        x ^= x >> 13;
        x = x.wrapping_mul(1664525).wrapping_add(1013904223);
    }
    std::io::stdout().write_all(&x.to_le_bytes())
}
