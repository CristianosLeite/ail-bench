# graph: reach every vertex of a graph, breadth first and depth first,
# with graphbfs and graphdfs of lib/algo/graph.aw, which write the
# vertices in the order they reach them.
use "bench/algo/harness.aw"
use "lib/algo/graph.aw"
ref graphbfs as a
ref graphdfs as b
call algograph a b
