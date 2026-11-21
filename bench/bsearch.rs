#[path = "algo/harness.rs"]
mod harness;

fn lower(a: &[u64], k: u64) -> usize {
    let (mut l, mut n) = (0, a.len());
    while l < n {
        let m = (l + n) >> 1;
        if a[m] < k {
            l = m + 1;
        } else {
            n = m;
        }
    }
    l
}

fn search(a: &[u64], q: &[u64], r: &mut [u64]) {
    for (i, &k) in q.iter().enumerate() {
        r[i] = lower(a, k) as u64;
    }
}

fn main() {
    harness::search(search);
}
