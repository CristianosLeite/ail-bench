use std::io::Write;

fn main() {
    let mut out = std::io::stdout();
    for i in 0..10u8 {
        let _ = out.write_all(&[b'0' + i, b'\n']);
    }
}
