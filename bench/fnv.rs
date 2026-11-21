use std::io::{Read, Write};

fn main() -> std::io::Result<()> {
    let mut d = Vec::new();
    std::io::stdin().take(1048576).read_to_end(&mut d)?;
    let mut h = 0xcbf29ce484222325_u64;
    for _ in 0..100 {
        for &x in &d {
            h = (h ^ x as u64).wrapping_mul(0x100000001b3);
        }
    }
    std::io::stdout().write_all(&h.to_le_bytes())
}
