#[path = "algo/harness.rs"]
mod harness;

fn total(a: &[f64], _b: &[f64], r: &mut [f64], n: usize) {
    if n != 0 {
        let mut s = 0.0;
        for i in 0..n * n {
            s += a[i];
        }
        r[0] = s;
    }
}

fn cols(a: &[f64], _b: &[f64], r: &mut [f64], n: usize) {
    for j in 0..n {
        r[j] = 0.0;
    }
    for i in 0..n {
        for j in 0..n {
            r[j] += a[i * n + j];
        }
    }
}

fn dot(a: &[f64], b: &[f64], r: &mut [f64], n: usize) {
    if n != 0 {
        let mut s = 0.0;
        for i in 0..n * n {
            s += a[i] * b[i];
        }
        r[0] = s;
    }
}

fn main() {
    harness::num(&[total, cols, dot], true);
}
