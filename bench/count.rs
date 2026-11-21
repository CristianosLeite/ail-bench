use std::io::{Read, Write};

fn main() {
    let mut b = [0u8; 4096];
    let ok = match std::io::stdin().read(&mut b) {
        Ok(n) => {
            let s = format!("{:04}\n", n);
            std::io::stdout().write_all(s.as_bytes()).is_ok()
        }
        Err(_) => false,
    };
    std::process::exit(!ok as i32);
}
