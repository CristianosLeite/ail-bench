#[path = "algo/harness.rs"]
mod harness;

fn pass(s: &[u64], d: &mut [u64], w: usize) {
    let n = s.len();
    let mut o = 0;
    while o < n {
        let m = (o + w).min(n);
        let h = (m + w).min(n);
        let (mut i, mut j, mut k) = (o, m, o);
        while i < m && j < h {
            if s[j] < s[i] {
                d[k] = s[j];
                j += 1;
            } else {
                d[k] = s[i];
                i += 1;
            }
            k += 1;
        }
        while i < m {
            d[k] = s[i];
            i += 1;
            k += 1;
        }
        while j < h {
            d[k] = s[j];
            j += 1;
            k += 1;
        }
        o = h;
    }
}

fn mergesort(a: &mut [u64], t: &mut [u64]) {
    let n = a.len();
    let mut w = 1;
    let mut flip = false;
    while w < n {
        if flip {
            pass(t, a, w);
        } else {
            pass(a, t, w);
        }
        flip = !flip;
        w *= 2;
    }
    if flip {
        a.copy_from_slice(t);
    }
}

fn main() {
    harness::sort(mergesort);
}
