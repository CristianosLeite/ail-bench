#[path = "algo/harness.rs"]
mod harness;

fn matmul(a: &[f64], b: &[f64], r: &mut [f64], n: usize) {
    for i in 0..n * n {
        r[i] = 0.0;
    }
    for i in 0..n {
        for k in 0..n {
            let x = a[i * n + k];
            for j in 0..n {
                r[i * n + j] += x * b[k * n + j];
            }
        }
    }
}

fn main() {
    harness::num(&[matmul], true);
}
