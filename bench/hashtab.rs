#[path = "algo/harness.rs"]
mod harness;

fn place(t: &[u64], m: usize, k: u64) -> usize {
    let mut h = (k.wrapping_mul(0x9e3779b97f4a7c15) >> 32) as usize & m;
    while t[h * 2] != 0 && t[h * 2] != k {
        h = (h + 1) & m;
    }
    h * 2
}

fn insert(t: &mut [u64], m: usize, q: &[u64]) {
    for (i, &k) in q.iter().enumerate() {
        let p = place(t, m, k);
        t[p] = k;
        t[p + 1] = i as u64;
    }
}

fn lookup(t: &[u64], m: usize, q: &[u64], r: &mut [u64]) {
    for (i, &k) in q.iter().enumerate() {
        let p = place(t, m, k);
        r[i] = if t[p] != 0 { t[p + 1] } else { u64::MAX };
    }
}

fn main() {
    harness::hash(insert, lookup, lookup);
}
