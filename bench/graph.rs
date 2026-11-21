#[path = "algo/harness.rs"]
mod harness;

fn bfs(off: &[u64], nbr: &[u64], seen: &mut [u8], r: &mut [u64]) {
    let (mut t, mut h) = (0, 0);
    for s in 0..seen.len() {
        if seen[s] != 0 {
            continue;
        }
        seen[s] = 1;
        r[t] = s as u64;
        t += 1;
        while h < t {
            let v = r[h] as usize;
            h += 1;
            for e in off[v] as usize..off[v + 1] as usize {
                let w = nbr[e];
                if seen[w as usize] == 0 {
                    seen[w as usize] = 1;
                    r[t] = w;
                    t += 1;
                }
            }
        }
    }
}

fn dfs(off: &[u64], nbr: &[u64], seen: &mut [u8], st: &mut [u64], r: &mut [u64]) {
    let mut k = 0;
    for s in 0..seen.len() {
        if seen[s] != 0 {
            continue;
        }
        st[0] = s as u64;
        let mut p = 1;
        while p > 0 {
            p -= 1;
            let v = st[p] as usize;
            if seen[v] != 0 {
                continue;
            }
            seen[v] = 1;
            r[k] = v as u64;
            k += 1;
            for e in off[v] as usize..off[v + 1] as usize {
                st[p] = nbr[e];
                p += 1;
            }
        }
    }
}

fn main() {
    harness::graph(bfs, dfs);
}
