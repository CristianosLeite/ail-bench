#[path = "algo/harness.rs"]
mod harness;
use std::cmp::Reverse;
use std::collections::{BinaryHeap, VecDeque};

// Vec, VecDeque and BinaryHeap of the standard library
fn vec(a: &[u64], r: &mut [u64], _: &mut ()) {
    let mut v = Vec::new();
    for &x in a {
        v.push(x);
    }
    for p in r.iter_mut() {
        *p = v.pop().unwrap();
    }
}

fn queue(a: &[u64], r: &mut [u64], _: &mut ()) {
    let mut q = VecDeque::with_capacity(1024);
    let mut j = 0;
    for &x in a {
        if q.len() == 1024 {
            r[j] = q.pop_front().unwrap();
            j += 1;
        }
        q.push_back(x);
    }
    while let Some(x) = q.pop_front() {
        r[j] = x;
        j += 1;
    }
}

fn heap(a: &[u64], r: &mut [u64], _: &mut ()) {
    let mut h = BinaryHeap::new();
    for &x in a {
        h.push(Reverse(x));
    }
    for p in r.iter_mut() {
        *p = h.pop().unwrap().0;
    }
}

fn main() {
    harness::coll::<()>(&[vec, queue, heap]);
}
