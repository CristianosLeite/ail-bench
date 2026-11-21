#[path = "algo/harness.rs"]
mod harness;

fn sift(a: &mut [u64], mut i: usize, v: u64) {
    let n = a.len();
    let mut c = i * 2 + 1;
    while c < n {
        let mut x = a[c];
        if c + 1 < n && a[c + 1] > x {
            c += 1;
            x = a[c];
        }
        if x <= v {
            break;
        }
        a[i] = x;
        i = c;
        c = i * 2 + 1;
    }
    a[i] = v;
}

fn heapsort(a: &mut [u64], _t: &mut [u64]) {
    let mut n = a.len();
    let mut i = n >> 1;
    while i > 0 {
        i -= 1;
        let v = a[i];
        sift(a, i, v);
    }
    while n > 1 {
        n -= 1;
        let v = a[n];
        a[n] = a[0];
        sift(&mut a[..n], 0, v);
    }
}

fn main() {
    harness::sort(heapsort);
}
