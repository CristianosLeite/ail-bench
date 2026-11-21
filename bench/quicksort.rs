#[path = "algo/harness.rs"]
mod harness;

fn quicksort(a: &mut [u64], mut p: usize, mut q: usize) {
    while q - p > 1 {
        let (mut x, mut b, c) = (a[p], a[p + (q - p) / 2], a[q - 1]);
        if x > b {
            std::mem::swap(&mut x, &mut b);
        }
        if b > c {
            b = c;
            if x > b {
                b = x;
            }
        }
        let (mut l, mut i, mut g) = (p, p, q);
        while i < g {
            let v = a[i];
            if v < b {
                a[i] = a[l];
                a[l] = v;
                l += 1;
                i += 1;
            } else if v > b {
                g -= 1;
                a[i] = a[g];
                a[g] = v;
            } else {
                i += 1;
            }
        }
        if l - p < q - g {
            quicksort(a, p, l);
            p = g;
        } else {
            quicksort(a, g, q);
            q = l;
        }
    }
}

fn sort(a: &mut [u64], _t: &mut [u64]) {
    quicksort(a, 0, a.len());
}

fn main() {
    harness::sort(sort);
}
