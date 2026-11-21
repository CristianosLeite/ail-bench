use std::io::{Read, Write};

fn main() -> std::io::Result<()> {
    let mut bytes = [0_u8; 8];
    std::io::stdin().read_exact(&mut bytes)?;
    let mut x = u64::from_le_bytes(bytes);
    let mut a = [0_u64; 30000];
    for v in a.iter_mut() {
        x = x.wrapping_mul(0x5851f42d4c957f2d).wrapping_add(0x14057b7ef767814f);
        *v = x >> 33;
    }
    for i in 1..a.len() {
        let k = a[i];
        let mut j = i;
        while j > 0 && a[j - 1] > k {
            a[j] = a[j - 1];
            j -= 1;
        }
        a[j] = k;
    }
    let mut h = 0_u64;
    for v in a {
        h = h.wrapping_mul(31).wrapping_add(v);
    }
    std::io::stdout().write_all(&h.to_le_bytes())
}
