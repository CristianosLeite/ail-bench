#[path = "algo/harness.rs"]
mod harness;
use std::collections::HashMap;

// HashMap of the standard library, with the hash it has
fn put(a: &[u64], r: &mut [u64], m: &mut HashMap<u64, u64>) {
    for (i, &x) in a.iter().enumerate() {
        m.insert(x, i as u64);
    }
    if !a.is_empty() {
        r[0] = m.len() as u64;
    }
}

fn get(a: &[u64], r: &mut [u64], m: &mut HashMap<u64, u64>) {
    for (p, x) in r.iter_mut().zip(a) {
        *p = *m.get(x).unwrap_or(&0);
    }
}

fn del(a: &[u64], r: &mut [u64], m: &mut HashMap<u64, u64>) {
    for (p, x) in r.iter_mut().zip(a) {
        *p = m.remove(x).is_some() as u64;
    }
    *m = HashMap::new();
}

fn main() {
    harness::coll::<HashMap<u64, u64>>(&[put, get, del]);
}
