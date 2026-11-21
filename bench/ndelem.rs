#[path = "algo/harness.rs"]
mod harness;

fn add(a: &[f64], b: &[f64], r: &mut [f64], n: usize) {
    for i in 0..n {
        r[i] = a[i] + b[i];
    }
}

fn mul(a: &[f64], b: &[f64], r: &mut [f64], n: usize) {
    for i in 0..n {
        r[i] = a[i] * b[i];
    }
}

fn scale(a: &[f64], b: &[f64], r: &mut [f64], n: usize) {
    if n != 0 {
        let k = b[0];
        for i in 0..n {
            r[i] = a[i] * k;
        }
    }
}

fn main() {
    harness::num(&[add, mul, scale], false);
}
