use std::io::Write;

fn main() {
    let ok = std::io::stdout().write_all(b"hello world\n").is_ok();
    std::process::exit(!ok as i32);
}
