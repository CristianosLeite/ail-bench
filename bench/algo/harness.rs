// What the algorithm programs of bench/ share; bench/algo/harness.aw
// says what it does, and this is the same statement for statement. The
// clock is Instant::now, which calls the function of the vDSO that the
// harness in AIL finds and calls itself.
#![allow(dead_code)]
use std::convert::TryInto;
use std::io::{Read, Write};
use std::process::exit;
use std::time::Instant;

pub struct Input {
    rng: u64,
    pub n: usize,
    pub shape: u64,
    pub runs: u64,
    pub mode: u64,
}

impl Input {
    pub fn rnd(&mut self) -> u64 {
        self.rng = self.rng.wrapping_add(0x9e3779b97f4a7c15);
        let mut z = self.rng;
        z = (z ^ z >> 30).wrapping_mul(0xbf58476d1ce4e5b9);
        z = (z ^ z >> 27).wrapping_mul(0x94d049bb133111eb);
        z ^ z >> 31
    }
}

fn read() -> Input {
    let mut b = [0u8; 40];
    if std::io::stdin().read_exact(&mut b).is_err() {
        exit(1);
    }
    let w = |i: usize| u64::from_le_bytes(b[i * 8..i * 8 + 8].try_into().unwrap());
    if w(1) >> 28 != 0 {
        exit(1);
    }
    Input { rng: w(0), n: w(1) as usize, shape: w(2), runs: w(3), mode: w(4) }
}

fn write(words: &[u64]) {
    let mut b = Vec::with_capacity(words.len() * 8);
    for v in words {
        b.extend_from_slice(&v.to_le_bytes());
    }
    if std::io::stdout().write_all(&b).is_err() {
        exit(1);
    }
}

fn end(mode: u64, input: &[u64], result: &[u64], times: &[u64]) {
    if mode == 1 {
        return write(input);
    }
    if mode == 2 {
        return write(result);
    }
    let mut out = vec![0u64];
    for &v in result {
        out[0] = out[0].wrapping_mul(31).wrapping_add(v);
    }
    out.extend_from_slice(times);
    write(&out);
}

fn fill(i: &mut Input, a: &mut [u64]) {
    let mut x = 0u64;
    for p in a.iter_mut() {
        let mut v = i.rnd();
        if i.shape == 1 {
            x = x.wrapping_add(v & 0xffff);
            v = x;
        }
        if i.shape == 2 {
            x = x.wrapping_sub(v & 0xffff);
            v = x;
        }
        if i.shape == 3 {
            v &= 15;
        }
        *p = v;
    }
}

pub fn sort(k: fn(&mut [u64], &mut [u64])) {
    let mut i = read();
    let n = i.n;
    let mut a = vec![0u64; n];
    let mut t = vec![0u64; n];
    fill(&mut i, &mut a);
    let mut w = a.clone();
    let mut ns = 0;
    for _ in 0..i.runs {
        w.copy_from_slice(&a);
        let b = Instant::now();
        k(&mut w, &mut t);
        ns += b.elapsed().as_nanos() as u64;
    }
    end(i.mode, &a, &w, &[ns]);
}

pub fn search(k: fn(&[u64], &[u64], &mut [u64])) {
    let mut i = read();
    let n = i.n;
    let mut a = vec![0u64; n * 2];
    let mut w = vec![0u64; n];
    let mask = if i.shape == 3 { 1 } else { 0xffff };
    let mut x = 0u64;
    for j in 0..n {
        x = x.wrapping_add(i.rnd() & mask);
        a[j] = x;
    }
    for j in 0..n {
        a[n + j] = i.rnd() % x.wrapping_add(2);
    }
    let mut ns = 0;
    for _ in 0..i.runs {
        let b = Instant::now();
        k(&a[..n], &a[n..], &mut w);
        ns += b.elapsed().as_nanos() as u64;
    }
    end(i.mode, &a, &w, &[ns]);
}

pub fn hash(
    ki: fn(&mut [u64], usize, &[u64]),
    kh: fn(&[u64], usize, &[u64], &mut [u64]),
    km: fn(&[u64], usize, &[u64], &mut [u64]),
) {
    let mut i = read();
    let n = i.n;
    let mut c = 16;
    while c < n * 2 {
        c *= 2;
    }
    let mut a = vec![0u64; n * 2];
    let mut w = vec![0u64; n * 2];
    let mut t = vec![0u64; c * 2];
    for j in 0..n {
        let mut v = i.rnd();
        if i.shape == 3 {
            v &= 255;
        }
        a[j] = v | 1;
    }
    for j in 0..n {
        a[n + j] = (i.rnd() | 1) << 1;
    }
    let (mut x, mut y, mut z) = (0, 0, 0);
    for _ in 0..i.runs {
        t.fill(0);
        let b = Instant::now();
        ki(&mut t, c - 1, &a[..n]);
        x += b.elapsed().as_nanos() as u64;
        let b = Instant::now();
        kh(&t, c - 1, &a[..n], &mut w[..n]);
        y += b.elapsed().as_nanos() as u64;
        let b = Instant::now();
        km(&t, c - 1, &a[n..], &mut w[n..]);
        z += b.elapsed().as_nanos() as u64;
    }
    end(i.mode, &a, &w, &[x, y, z]);
}

pub fn graph(
    kb: fn(&[u64], &[u64], &mut [u8], &mut [u64]),
    kd: fn(&[u64], &[u64], &mut [u8], &mut [u64], &mut [u64]),
) {
    let mut i = read();
    let n = i.n;
    let mask = if i.shape == 3 { 1 } else { 15 };
    let mut a = vec![0u64; n + 1];
    let mut q = Vec::new();
    for j in 0..n {
        a[j] = q.len() as u64;
        for _ in 0..i.rnd() & mask {
            q.push(i.rnd() % n as u64);
        }
    }
    a[n] = q.len() as u64;
    let mut w = vec![0u64; n * 2];
    let mut t = vec![0u64; q.len() + 1];
    let mut z = vec![0u8; n];
    let (mut y, mut h) = (0, 0);
    for _ in 0..i.runs {
        z.fill(0);
        let b = Instant::now();
        kb(&a, &q, &mut z, &mut w[..n]);
        y += b.elapsed().as_nanos() as u64;
        z.fill(0);
        let b = Instant::now();
        kd(&a, &q, &mut z, &mut t, &mut w[n..]);
        h += b.elapsed().as_nanos() as u64;
    }
    let mut input = a.clone();
    input.extend_from_slice(&q);
    end(i.mode, &input, &w, &[y, h]);
}

pub type Kern = fn(&[f64], &[f64], &mut [f64], usize);

pub fn num(ks: &[Kern], sq: bool) {
    let mut i = read();
    let n = i.n;
    let mut l = n;
    if sq {
        l = n * n;
        if l >> 28 != 0 {
            exit(1);
        }
    }
    let mut a = vec![0u64; l * 2];
    fill(&mut i, &mut a);
    let x: Vec<f64> = a.iter().map(|&v| (v & 0xfffff) as i64 as f64 / 1024.0 - 512.0).collect();
    let mut r = vec![0f64; l * ks.len()];
    let mut ns = vec![0u64; ks.len()];
    for _ in 0..i.runs {
        for (j, k) in ks.iter().enumerate() {
            let b = Instant::now();
            k(&x[..l], &x[l..], &mut r[j * l..(j + 1) * l], n);
            ns[j] += b.elapsed().as_nanos() as u64;
        }
    }
    let input: Vec<u64> = x.iter().map(|v| v.to_bits()).collect();
    let result: Vec<u64> = r.iter().map(|v| v.to_bits()).collect();
    end(i.mode, &input, &result, &ns);
}

// The 64 bytes of the others are a value of the program's choosing here,
// made anew for each run and dropped behind the clock.
pub fn coll<S: Default>(ks: &[fn(&[u64], &mut [u64], &mut S)]) {
    let mut i = read();
    let n = i.n;
    let mut a = vec![0u64; n];
    fill(&mut i, &mut a);
    let mut w = vec![0u64; n * ks.len()];
    let mut ns = vec![0u64; ks.len()];
    for _ in 0..i.runs {
        let mut st = S::default();
        for (j, k) in ks.iter().enumerate() {
            let b = Instant::now();
            k(&a, &mut w[j * n..(j + 1) * n], &mut st);
            ns[j] += b.elapsed().as_nanos() as u64;
        }
    }
    end(i.mode, &a, &w, &ns);
}
